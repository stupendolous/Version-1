<?php
/**
 * Agent loop for the AI departments (Claude Messages API + tool use).
 *
 * Every API message is stored verbatim (wire-format JSON) in ai_messages, so a
 * conversation can be continued later exactly as the model produced it.
 */
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../lib/business.php';
require_once __DIR__ . '/company.php';
require_once __DIR__ . '/tools.php';

use Anthropic\Client;

function ai_client(): Client
{
    static $client = null;
    // Reads ANTHROPIC_API_KEY from the environment (or .env via config.php).
    return $client ??= new Client(
        apiKey: getenv('ANTHROPIC_API_KEY') ?: null,
        baseUrl: getenv('ANTHROPIC_BASE_URL') ?: null,
    );
}

function ai_configured(): bool
{
    return (bool)getenv('ANTHROPIC_API_KEY');
}

function system_prompt(string $dept): array
{
    return [[
        'type' => 'text',
        'text' => SHARED_RULES . "\n\n# Your role\n" . DEPARTMENTS[$dept]['system'] . "\n\n# Company\n" . company_profile(),
        'cacheControl' => ['type' => 'ephemeral'],
    ]];
}

/** Briefing given at the start of every new conversation. */
function briefing(string $dept): string
{
    $out = "# Briefing\nToday is " . date('l, j F Y') . ".\n\n## Last 30 days\n" . json_encode(business_kpis(30), JSON_PRETTY_PRINT) . "\n";

    $tasks = rows("SELECT id, title, priority, due_on, status FROM ai_tasks WHERE department = ? AND status != 'done' ORDER BY id DESC LIMIT 10", [$dept]);
    if ($tasks) {
        $out .= "\n## Your open tasks\n" . implode("\n", array_map(fn($t) => "- #{$t['id']} [{$t['priority']}, {$t['status']}] {$t['title']}" . ($t['due_on'] ? " (due {$t['due_on']})" : ''), $tasks)) . "\n";
    }
    $acts = rows("SELECT id, summary, status, decided_by FROM ai_actions WHERE department = ? ORDER BY id DESC LIMIT 10", [$dept]);
    if ($acts) {
        $out .= "\n## Your recent proposals and the owner's decisions\n" . implode("\n", array_map(fn($a) => "- #{$a['id']} {$a['status']}: {$a['summary']}", $acts)) . "\n";
    }
    $reports = rows('SELECT r.department, r.content, r.created_at FROM ai_reports r
                     JOIN (SELECT department, MAX(id) id FROM ai_reports GROUP BY department) m ON m.id = r.id');
    foreach ($reports as $r) {
        if (!isset(DEPARTMENTS[$r['department']])) continue;
        $who = $r['department'] === $dept ? 'Your own last report' : 'Latest report from ' . DEPARTMENTS[$r['department']]['name'];
        $out .= "\n## $who ({$r['created_at']})\n" . mb_strimwidth($r['content'], 0, 2500, "\n…(truncated)") . "\n";
    }
    return $out;
}

function store_message(int $convId, string $role, array $content, ?string $display = null): void
{
    q('INSERT INTO ai_messages (conversation_id, role, content, display_text) VALUES (?, ?, ?, ?)',
      [$convId, $role, json_encode($content, JSON_UNESCAPED_UNICODE), $display]);
}

/**
 * Run a department on a message. Starts a new conversation when $conversationId is null.
 * Returns ['text', 'conversation_id', 'tools' => [[name, input, result, error]], 'input_tokens', 'output_tokens'].
 */
function run_agent(string $dept, string $text, ?int $conversationId = null, string $kind = 'chat', ?int $cycleId = null, int $depth = 0): array
{
    if (!isset(DEPARTMENTS[$dept])) {
        throw new InvalidArgumentException("Unknown department $dept");
    }
    if (!ai_configured()) {
        throw new RuntimeException('No Anthropic API key. Add ANTHROPIC_API_KEY to the .env file.');
    }

    $messages = [];
    if ($conversationId) {
        foreach (rows('SELECT role, content FROM ai_messages WHERE conversation_id = ? ORDER BY id', [$conversationId]) as $m) {
            $messages[] = ['role' => $m['role'], 'content' => json_decode($m['content'], true)];
        }
        $userContent = [['type' => 'text', 'text' => '[' . date('D j M, H:i') . "]\n" . $text]];
    } else {
        q('INSERT INTO ai_conversations (department, kind, cycle_id) VALUES (?, ?, ?)', [$dept, $kind, $cycleId]);
        $conversationId = insert_id();
        $userContent = [['type' => 'text', 'text' => briefing($dept) . "\n# Message\n" . $text]];
    }
    $messages[] = ['role' => 'user', 'content' => $userContent];
    store_message($conversationId, 'user', $userContent, $text);

    $tools = tools_for($dept, $depth === 0);
    $calls = [];
    $inTok = $outTok = 0;
    $final = '';

    for ($step = 1; ; $step++) {
        $lastStep = $step > AI_MAX_STEPS;
        $response = ai_client()->beta->messages->create(
            model: AI_MODEL,
            maxTokens: 16000,
            system: system_prompt($dept),
            tools: $tools,
            toolChoice: $lastStep ? ['type' => 'none'] : null,
            outputConfig: ['effort' => DEPARTMENTS[$dept]['effort']],
            // If the model declines, retry server-side on the default fallback model.
            fallbacks: 'default',
            betas: ['server-side-fallback-2026-07-01'],
            messages: $messages,
        );
        $inTok += (int)$response->usage->inputTokens + (int)($response->usage->cacheReadInputTokens ?? 0) + (int)($response->usage->cacheCreationInputTokens ?? 0);
        $outTok += (int)$response->usage->outputTokens;

        $assistant = json_decode(json_encode($response->content), true);
        $messages[] = ['role' => 'assistant', 'content' => $assistant];
        store_message($conversationId, 'assistant', $assistant);

        foreach ($assistant as $b) {
            if ($b['type'] === 'text') $final = $b['text'];
        }

        if ($response->stopReason !== 'tool_use') {
            if ($response->stopReason === 'refusal') {
                $final = '_This request was declined by the model. Try rephrasing it._';
            } elseif ($response->stopReason === 'max_tokens') {
                $final .= "\n\n_(Reply cut off at the length limit.)_";
            }
            break;
        }

        $results = [];
        foreach ($assistant as $b) {
            if ($b['type'] !== 'tool_use') continue;
            [$out, $isError] = run_tool($dept, $b['name'], (array)$b['input'], $conversationId, $depth);
            $results[] = ['type' => 'tool_result', 'tool_use_id' => $b['id'], 'content' => $out, 'is_error' => $isError];
            $calls[] = ['name' => $b['name'], 'input' => $b['input'], 'result' => mb_strimwidth($out, 0, 400, '…'), 'error' => $isError];
        }
        if ($step >= AI_MAX_STEPS) {
            $results[] = ['type' => 'text', 'text' => 'Step limit reached. Stop using tools and give your final answer now.'];
        }
        $messages[] = ['role' => 'user', 'content' => $results];
        store_message($conversationId, 'user', $results);
        if ($lastStep) break;
    }

    q('UPDATE ai_conversations SET input_tokens = input_tokens + ?, output_tokens = output_tokens + ? WHERE id = ?', [$inTok, $outTok, $conversationId]);
    return ['text' => $final, 'conversation_id' => $conversationId, 'tools' => $calls, 'input_tokens' => $inTok, 'output_tokens' => $outTok];
}

/** Chat history for display: owner messages, tool calls, and department replies. */
function conversation_view(int $conversationId): array
{
    $items = [];
    foreach (rows('SELECT role, content, display_text, created_at FROM ai_messages WHERE conversation_id = ? ORDER BY id', [$conversationId]) as $m) {
        $content = json_decode($m['content'], true);
        if ($m['role'] === 'user') {
            if ($m['display_text'] !== null) {
                $items[] = ['type' => 'user', 'text' => $m['display_text'], 'at' => $m['created_at']];
            }
            continue;
        }
        foreach ($content as $b) {
            if ($b['type'] === 'tool_use') {
                $items[] = ['type' => 'tool', 'name' => $b['name'], 'input' => $b['input']];
            } elseif ($b['type'] === 'text' && trim($b['text']) !== '') {
                $items[] = ['type' => 'assistant', 'text' => $b['text'], 'at' => $m['created_at']];
            }
        }
    }
    return $items;
}

const CYCLE_INSTRUCTION = <<<TXT
Run your weekly review now. Investigate the business with your tools, take the actions you judge right
(action tools go to the owner's approval queue), and create tasks for follow-up work. Then write your
report for the owner in Markdown: what you found (with numbers), what you proposed or created and why,
and what you need from the owner. Keep it under 500 words.
TXT;

/** Full company cycle: every department reviews the business in turn, then the CEO plans. */
function run_cycle(string $directive = '', ?int $cycleId = null, ?callable $log = null): int
{
    $log ??= fn(string $s) => null;
    if (!$cycleId) {
        q('INSERT INTO ai_cycles (directive) VALUES (?)', [$directive]);
        $cycleId = insert_id();
    }
    $status = 'failed';
    try {
        foreach (CYCLE_ORDER as $dept) {
            $name = DEPARTMENTS[$dept]['name'];
            q('UPDATE ai_cycles SET progress = ? WHERE id = ?', ["$name is working…", $cycleId]);
            $log("$name started");
            $msg = CYCLE_INSTRUCTION;
            if ($dept === 'ceo') {
                $msg .= "\nAll departments have just reported (see briefing). Delegate only if something important is missing. Write the company plan as your report.";
            }
            if (trim($directive) !== '') {
                $msg .= "\n\nDirective from the owner for this cycle: " . trim($directive);
            }
            $r = run_agent($dept, $msg, null, 'cycle', $cycleId);
            q('INSERT INTO ai_reports (cycle_id, department, content, input_tokens, output_tokens) VALUES (?, ?, ?, ?, ?)',
              [$cycleId, $dept, $r['text'], $r['input_tokens'], $r['output_tokens']]);
            $log("$name done (" . count($r['tools']) . ' tool calls)');
        }
        $status = 'done';
        q('UPDATE ai_cycles SET progress = ? WHERE id = ?', ['Complete', $cycleId]);
    } catch (Throwable $e) {
        q('UPDATE ai_cycles SET progress = ? WHERE id = ?', [mb_strimwidth('Error: ' . $e->getMessage(), 0, 200), $cycleId]);
        $log('Failed: ' . $e->getMessage());
        throw $e;
    } finally {
        q('UPDATE ai_cycles SET status = ?, finished_at = NOW() WHERE id = ?', [$status, $cycleId]);
    }
    return $cycleId;
}

/** Start a cycle in a background process so the web page doesn't wait for it. */
function start_cycle_background(string $directive): int
{
    q('INSERT INTO ai_cycles (directive, progress) VALUES (?, ?)', [$directive, 'Starting…']);
    $id = insert_id();
    $php = PHP_BINARY;
    if (!preg_match('/php[\d.]*(\.exe)?$/i', basename($php))) {
        $php = PHP_BINDIR . DIRECTORY_SEPARATOR . 'php';
    }
    $script = realpath(__DIR__ . '/../bin/run_cycle.php');
    $logFile = __DIR__ . '/../storage/cycle.log';
    @mkdir(dirname($logFile), 0775, true);
    $cmd = escapeshellarg($php) . ' ' . escapeshellarg($script) . ' --cycle=' . $id;
    if (DIRECTORY_SEPARATOR === '\\') {
        pclose(popen("start /B \"\" $cmd >> " . escapeshellarg($logFile) . ' 2>&1', 'r'));
    } else {
        exec("$cmd >> " . escapeshellarg($logFile) . ' 2>&1 &');
    }
    return $id;
}
