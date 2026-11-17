<?php
/**
 * STRGarage AI engine: runs each department through Claude and stores the reports.
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/company.php';

use Anthropic\Client;

function ai_client(): Client
{
    static $client = null;
    // Reads ANTHROPIC_API_KEY from the environment.
    return $client ??= new Client();
}

/** Live snapshot of the inventory database, as text for the departments. */
function company_snapshot(mysqli $db): string
{
    $rows = [];
    $result = mysqli_query($db, 'SELECT kod_produk, nama_produk, harga, kuantiti FROM jadualmainan ORDER BY kod_produk');
    while ($result && ($r = mysqli_fetch_assoc($result))) {
        $rows[] = $r;
    }

    $units = array_sum(array_map(fn($r) => (int)$r['kuantiti'], $rows));
    $value = array_sum(array_map(fn($r) => (float)$r['harga'] * (int)$r['kuantiti'], $rows));

    $lines = ["Inventory snapshot taken " . date('Y-m-d H:i'),
              "Products: " . count($rows) . " | Total units: $units | Stock value: RM " . number_format($value, 2),
              "", "code | name | price (RM) | qty"];
    foreach ($rows as $r) {
        $lines[] = "{$r['kod_produk']} | {$r['nama_produk']} | {$r['harga']} | {$r['kuantiti']}";
    }
    return implode("\n", $lines);
}

/** Most recent report per department, for the dashboard and for context. */
function latest_reports(mysqli $db, ?int $cycleId = null): array
{
    $out = [];
    if ($cycleId) {
        $stmt = mysqli_prepare($db, 'SELECT * FROM ai_reports WHERE cycle_id = ? ORDER BY id');
        mysqli_stmt_bind_param($stmt, 'i', $cycleId);
    } else {
        $stmt = mysqli_prepare($db, 'SELECT r.* FROM ai_reports r JOIN (SELECT department, MAX(id) id FROM ai_reports GROUP BY department) m ON r.id = m.id');
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($r = mysqli_fetch_assoc($res)) {
        $out[$r['department']] = $r;
    }
    return $out;
}

/**
 * Run one department. $inputs are earlier reports (dept key => text) it should read.
 * Returns the stored report row.
 */
function run_department(mysqli $db, string $dept, array $inputs = [], string $directive = '', ?int $cycleId = null): array
{
    if (!isset(DEPARTMENTS[$dept])) {
        throw new InvalidArgumentException("Unknown department: $dept");
    }
    $def = DEPARTMENTS[$dept];

    $prompt = "## Company inventory data\n" . company_snapshot($db) . "\n";
    foreach ($inputs as $key => $text) {
        $prompt .= "\n## Report from " . DEPARTMENTS[$key]['name'] . "\n$text\n";
    }
    if (trim($directive) !== '') {
        $prompt .= "\n## Directive from the owner\n" . trim($directive) . "\n";
    }
    $prompt .= "\nWrite your report now in Markdown.";

    $message = ai_client()->beta->messages->create(
        model: AI_MODEL,
        maxTokens: 16000,
        system: [[
            'type' => 'text',
            'text' => $def['system'] . "\n\n# Company\n" . COMPANY_NAME . "\n" . COMPANY_PROFILE,
            'cacheControl' => ['type' => 'ephemeral'],
        ]],
        outputConfig: ['effort' => $def['effort']],
        // If the model declines a request, retry server-side on the default fallback model.
        fallbacks: 'default',
        betas: ['server-side-fallback-2026-07-01'],
        messages: [['role' => 'user', 'content' => $prompt]],
    );

    $text = '';
    foreach ($message->content as $block) {
        if ($block->type === 'text') {
            $text .= $block->text;
        }
    }
    if ($message->stopReason === 'refusal') {
        $text = "_The {$def['name']} department declined this request._";
    } elseif ($message->stopReason === 'max_tokens') {
        $text .= "\n\n_(Report cut off at the length limit.)_";
    }

    $in = (int)$message->usage->inputTokens;
    $outTok = (int)$message->usage->outputTokens;
    $stmt = mysqli_prepare($db, 'INSERT INTO ai_reports (cycle_id, department, content, input_tokens, output_tokens) VALUES (?, ?, ?, ?, ?)');
    mysqli_stmt_bind_param($stmt, 'issii', $cycleId, $dept, $text, $in, $outTok);
    mysqli_stmt_execute($stmt);

    return ['department' => $dept, 'content' => $text, 'input_tokens' => $in, 'output_tokens' => $outTok];
}

/**
 * Full company cycle: Analytics -> Marketing, R&D, HR -> CEO plan.
 * $log receives progress lines (department name) as each one finishes.
 */
function run_cycle(mysqli $db, string $directive = '', ?callable $log = null): int
{
    $log ??= fn(string $s) => null;

    $stmt = mysqli_prepare($db, 'INSERT INTO ai_cycles (directive) VALUES (?)');
    mysqli_stmt_bind_param($stmt, 's', $directive);
    mysqli_stmt_execute($stmt);
    $cycleId = (int)mysqli_insert_id($db);

    try {
        $reports = [];
        $reports['analytics'] = run_department($db, 'analytics', [], $directive, $cycleId)['content'];
        $log('Analytics done');
        foreach (['marketing', 'rnd'] as $d) {
            $reports[$d] = run_department($db, $d, ['analytics' => $reports['analytics']], $directive, $cycleId)['content'];
            $log(DEPARTMENTS[$d]['name'] . ' done');
        }
        // HR staffs whatever Marketing and R&D plan to do.
        $reports['hr'] = run_department($db, 'hr', $reports, $directive, $cycleId)['content'];
        $log('HR done');
        run_department($db, 'ceo', $reports, $directive, $cycleId);
        $log('CEO plan done');
        $status = 'done';
    } catch (Throwable $e) {
        $status = 'failed';
        $log('Failed: ' . $e->getMessage());
        throw $e;
    } finally {
        $stmt = mysqli_prepare($db, 'UPDATE ai_cycles SET status = ?, finished_at = NOW() WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'si', $status, $cycleId);
        mysqli_stmt_execute($stmt);
    }
    return $cycleId;
}
