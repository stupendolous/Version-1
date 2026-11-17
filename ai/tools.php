<?php
/**
 * Tools the AI departments can call: read the business data, propose actions,
 * create tasks and (CEO only) delegate to departments.
 */
require_once __DIR__ . '/../lib/business.php';
require_once __DIR__ . '/actions.php';

const DB_SCHEMA_DOC = <<<TXT
MySQL tables (read-only access):
customers(id, name, phone, email, source, notes, created_at) - source: tiktok, walk-in, referral, google, instagram, facebook
vehicles(id, customer_id, plate, make, model, year, mileage, created_at)
services(id, name, category, price, labour_hours, active) - the service menu
parts(id, sku, name, category, cost, price, qty, reorder_level, supplier) - stock on hand
employees(id, name, role, phone, hourly_rate, hired_on, status)
jobs(id, customer_id, vehicle_id, employee_id, status, complaint, notes, total, opened_at, closed_at) - work orders; status: open, in_progress, completed, cancelled; revenue = SUM(total) of completed jobs by closed_at; employee_id = assigned mechanic
job_items(id, job_id, item_type, ref_id, description, qty, unit_price, unit_cost) - item_type 'service' (ref_id -> services.id) or 'part' (ref_id -> parts.id)
expenses(id, category, amount, description, spent_on) - category: rent, utilities, salaries, parts, marketing, equipment, other
purchase_orders(id, part_id, qty, unit_cost, status, created_at, received_at)
marketing_posts(id, channel, caption, video_idea, scheduled_for, status, created_at)
promotions(id, title, description, service_id, discount_percent, start_date, end_date, status)
job_postings(id, title, employment_type, pay_range, description, status, created_at)
ai_actions(id, department, type, summary, reason, status, result, created_at, decided_at) - proposals and their outcome
ai_tasks(id, department, title, details, priority, status, created_by, due_on, created_at)
ai_reports(id, cycle_id, department, content, created_at) - past department reports
TXT;

function obj(array $props, string $desc = ''): array
{
    return ['type' => 'object', 'properties' => $props, 'required' => array_keys($props), 'additionalProperties' => false];
}

function all_tool_defs(): array
{
    $s = fn(string $d) => ['type' => 'string', 'description' => $d];
    $reason = $s('Why, with the evidence (numbers) behind it.');
    $date = fn(string $d) => ['type' => ['string', 'null'], 'description' => "$d Format YYYY-MM-DD, or null."];

    return [
        'query_database' => [
            'description' => "Run one read-only SQL SELECT against the garage database and get rows back as JSON (max 200 rows). Use aggregates (SUM, COUNT, GROUP BY) rather than pulling raw rows.\n\n" . DB_SCHEMA_DOC,
            'schema' => obj(['sql' => $s('A single MySQL SELECT (or WITH ... SELECT) statement.')]),
        ],
        'get_kpis' => [
            'description' => 'Headline business numbers for the last N days: revenue and change vs previous period, completed jobs, average ticket, gross margin, expenses, open jobs, new customers, low-stock count, staff, pending approvals.',
            'schema' => obj(['days' => ['type' => 'integer', 'description' => 'Period length in days, e.g. 7, 30, 90.']]),
        ],
        'create_task' => [
            'description' => 'Add a task to the company task board for a department (or the owner). Use for follow-up work that is not a direct change to the business.',
            'schema' => obj([
                'department' => ['type' => 'string', 'enum' => ['ceo', 'analytics', 'marketing', 'rnd', 'hr', 'owner']],
                'title' => $s('Short, actionable title.'),
                'details' => $s('What to do and what "done" looks like.'),
                'priority' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
                'due_on' => $date('Due date.'),
            ]),
        ],
        'delegate_to' => [
            'description' => 'Give a department a concrete instruction and get its answer back. The department can query data, propose actions and create tasks. Use one call per department per topic.',
            'schema' => obj([
                'department' => ['type' => 'string', 'enum' => ['analytics', 'marketing', 'rnd', 'hr']],
                'instruction' => $s('Specific instruction, including the context and output you need.'),
            ]),
        ],
        'propose_restock' => [
            'description' => 'Propose buying more of a part (creates a purchase order once approved).',
            'action' => 'restock',
            'schema' => obj(['part_id' => ['type' => 'integer'], 'quantity' => ['type' => 'integer'], 'reason' => $reason]),
        ],
        'propose_price_change' => [
            'description' => 'Propose a new price for a service or a part.',
            'action' => 'price_change',
            'schema' => obj(['item_type' => ['type' => 'string', 'enum' => ['service', 'part']], 'item_id' => ['type' => 'integer'],
                             'new_price' => ['type' => 'number'], 'reason' => $reason]),
        ],
        'draft_social_post' => [
            'description' => 'Propose a social media post for the content calendar.',
            'action' => 'social_post',
            'schema' => obj([
                'channel' => ['type' => 'string', 'enum' => ['tiktok', 'instagram', 'facebook', 'whatsapp']],
                'caption' => $s('Ready-to-paste caption with hashtags (BM first, then English).'),
                'video_idea' => $s('Shot list / hook for the video or image, short.'),
                'scheduled_for' => $date('Day to post.'),
                'reason' => $reason,
            ]),
        ],
        'propose_promotion' => [
            'description' => 'Propose a time-limited promotion, optionally tied to one service.',
            'action' => 'promotion',
            'schema' => obj([
                'title' => $s('Promotion name.'), 'description' => $s('Terms customers will see.'),
                'service_id' => ['type' => ['integer', 'null'], 'description' => 'Service it applies to, or null for general.'],
                'discount_percent' => ['type' => 'number'],
                'start_date' => $s('YYYY-MM-DD'), 'end_date' => $s('YYYY-MM-DD'), 'reason' => $reason,
            ]),
        ],
        'propose_new_service' => [
            'description' => 'Propose adding a new service or package to the menu.',
            'action' => 'new_service',
            'schema' => obj(['name' => $s('Service name.'), 'category' => $s('Category, e.g. Servicing, Brakes, Tyres, Aircond, Electrical, Diagnostics, Suspension, Package.'),
                             'price' => ['type' => 'number'], 'labour_hours' => ['type' => 'number'], 'reason' => $reason]),
        ],
        'propose_job_posting' => [
            'description' => 'Propose opening a job posting.',
            'action' => 'job_posting',
            'schema' => obj(['title' => $s('Role title.'), 'employment_type' => ['type' => 'string', 'enum' => ['full-time', 'part-time', 'contract', 'internship']],
                             'pay_range' => $s('e.g. "RM 2,000 - 2,600 / month"'), 'description' => $s('Ready-to-post ad, BM and English.'), 'reason' => $reason]),
        ],
        'propose_pay_change' => [
            'description' => "Propose changing an employee's hourly rate. Always needs the owner's approval.",
            'action' => 'pay_change',
            'schema' => obj(['employee_id' => ['type' => 'integer'], 'new_hourly_rate' => ['type' => 'number'], 'reason' => $reason]),
        ],
    ];
}

/** API tool definitions for one department. */
function tools_for(string $dept, bool $canDelegate = true): array
{
    $names = array_merge(['query_database', 'get_kpis', 'create_task'], DEPARTMENTS[$dept]['tools']);
    if (!$canDelegate) {
        $names = array_values(array_diff($names, ['delegate_to']));
    }
    $defs = all_tool_defs();
    $tools = [];
    foreach ($names as $n) {
        $tools[] = ['name' => $n, 'description' => $defs[$n]['description'], 'inputSchema' => $defs[$n]['schema'], 'strict' => true];
    }
    return $tools;
}

/** Execute a tool call. Returns [string result, bool isError]. */
function run_tool(string $dept, string $name, array $input, int $conversationId, int $depth): array
{
    $defs = all_tool_defs();
    $allowed = array_column(tools_for($dept, $depth === 0), 'name');
    if (!in_array($name, $allowed, true)) {
        return ["Tool $name is not available to this department.", true];
    }
    try {
        if (isset($defs[$name]['action'])) {
            $reason = (string)($input['reason'] ?? '');
            [$id, $status, $msg] = propose_action($dept, $defs[$name]['action'], $input, $reason, $conversationId);
            return [$msg, false];
        }
        switch ($name) {
            case 'query_database':
                return [read_only_query((string)$input['sql']), false];
            case 'get_kpis':
                return [json_encode(business_kpis(max(1, min(365, (int)$input['days'])))), false];
            case 'create_task':
                q('INSERT INTO ai_tasks (department, title, details, priority, due_on, created_by) VALUES (?, ?, ?, ?, ?, ?)',
                  [$input['department'], $input['title'], $input['details'], $input['priority'], $input['due_on'] ?: null, $dept]);
                return ['Task #' . insert_id() . ' created.', false];
            case 'delegate_to':
                $res = run_agent($input['department'], $input['instruction'], null, 'delegated', null, $depth + 1);
                return [DEPARTMENTS[$input['department']]['name'] . " replied:\n\n" . $res['text'], false];
        }
    } catch (InvalidArgumentException $e) {
        return [$e->getMessage(), true];
    } catch (mysqli_sql_exception $e) {
        return ['SQL error: ' . $e->getMessage(), true];
    }
    return ["Unknown tool $name", true];
}

/** Run a single SELECT inside a read-only transaction. */
function read_only_query(string $sql): string
{
    $sql = rtrim(trim($sql), "; \n\t");
    if (!preg_match('/^\s*(select|with)\b/i', $sql)) {
        throw new InvalidArgumentException('Only SELECT queries are allowed.');
    }
    if (str_contains($sql, ';')) {
        throw new InvalidArgumentException('Only one statement is allowed.');
    }
    if (preg_match('/\b(users|settings|ai_messages|ai_conversations|information_schema|mysql|performance_schema)\b|\binto\s+(out|dump)file\b|\bload_file\b|\bsleep\s*\(|\bbenchmark\s*\(|\bfor\s+update\b/i', $sql)) {
        throw new InvalidArgumentException('That table or function is not available.');
    }
    $db = db();
    try {
        $db->query('SET SESSION max_statement_time = 5');   // MariaDB
    } catch (mysqli_sql_exception) {
        try { $db->query('SET SESSION MAX_EXECUTION_TIME = 5000'); } catch (mysqli_sql_exception) {} // MySQL
    }
    $db->query('START TRANSACTION READ ONLY');
    try {
        $res = $db->query($sql);
        $rows = [];
        while (($r = $res->fetch_assoc()) && count($rows) < 200) {
            $rows[] = $r;
        }
        $total = $res->num_rows;
    } finally {
        $db->query('ROLLBACK');
    }
    $out = json_encode($rows, JSON_UNESCAPED_UNICODE);
    return $total > 200 ? "$out\n(Showing 200 of $total rows - aggregate instead.)" : ($rows ? $out : '[] (no rows)');
}
