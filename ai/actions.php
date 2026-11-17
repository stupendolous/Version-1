<?php
/**
 * Actions the AI departments can propose. A proposal is validated and stored
 * as `pending`; it changes the business only when the owner approves it, or
 * immediately when the owner has put that action type on autopilot.
 */
require_once __DIR__ . '/../lib/db.php';

const ACTION_TYPES = [
    'restock'      => ['label' => 'Restock parts',        'autopilot' => true],
    'social_post'  => ['label' => 'Schedule social post', 'autopilot' => true],
    'promotion'    => ['label' => 'Start promotion',      'autopilot' => true],
    'price_change' => ['label' => 'Change a price',       'autopilot' => true],
    'new_service'  => ['label' => 'Add new service',      'autopilot' => true],
    'job_posting'  => ['label' => 'Open job posting',     'autopilot' => true],
    // Decisions about a person's pay always need the owner.
    'pay_change'   => ['label' => 'Change staff pay',     'autopilot' => false],
];

function autopilot_on(string $type): bool
{
    return (ACTION_TYPES[$type]['autopilot'] ?? false) && setting("autopilot_$type", '0') === '1';
}

/**
 * Validate a proposal and build its human summary.
 * Returns [summary, normalized payload]. Throws InvalidArgumentException with a
 * message the model can act on.
 */
function describe_action(string $type, array $p): array
{
    switch ($type) {
        case 'restock':
            $part = row('SELECT * FROM parts WHERE id = ?', [(int)$p['part_id']]);
            if (!$part) throw new InvalidArgumentException("No part with id {$p['part_id']}.");
            $qty = (int)$p['quantity'];
            if ($qty < 1 || $qty > 1000) throw new InvalidArgumentException('Quantity must be 1-1000.');
            return ["Restock {$qty} × {$part['name']} ({$part['sku']}) - est. " . rm($qty * $part['cost']),
                    ['part_id' => (int)$part['id'], 'quantity' => $qty]];

        case 'price_change':
            $table = $p['item_type'] === 'part' ? 'parts' : 'services';
            $item = row("SELECT id, name, price FROM $table WHERE id = ?", [(int)$p['item_id']]);
            if (!$item) throw new InvalidArgumentException("No {$p['item_type']} with id {$p['item_id']}.");
            $new = round((float)$p['new_price'], 2);
            if ($new <= 0) throw new InvalidArgumentException('Price must be positive.');
            return ["Change price of {$item['name']}: " . rm($item['price']) . ' → ' . rm($new),
                    ['item_type' => $p['item_type'] === 'part' ? 'part' : 'service', 'item_id' => (int)$item['id'], 'new_price' => $new]];

        case 'social_post':
            $ch = strtolower($p['channel']);
            return ['Schedule ' . ucfirst($ch) . ' post for ' . ($p['scheduled_for'] ?: 'next available day') . ': "' . mb_strimwidth($p['caption'], 0, 70, '…') . '"',
                    ['channel' => $ch, 'caption' => $p['caption'], 'video_idea' => $p['video_idea'] ?? '', 'scheduled_for' => $p['scheduled_for'] ?: null]];

        case 'promotion':
            $svc = null;
            if (!empty($p['service_id'])) {
                $svc = row('SELECT id, name FROM services WHERE id = ?', [(int)$p['service_id']]);
                if (!$svc) throw new InvalidArgumentException("No service with id {$p['service_id']}.");
            }
            $pct = (float)$p['discount_percent'];
            if ($pct < 0 || $pct > 50) throw new InvalidArgumentException('Discount must be 0-50%.');
            return ["Promotion \"{$p['title']}\"" . ($svc ? " on {$svc['name']}" : '') . " - {$pct}% off, {$p['start_date']} to {$p['end_date']}",
                    ['title' => $p['title'], 'description' => $p['description'], 'service_id' => $svc ? (int)$svc['id'] : null,
                     'discount_percent' => $pct, 'start_date' => $p['start_date'], 'end_date' => $p['end_date']]];

        case 'new_service':
            $price = round((float)$p['price'], 2);
            if ($price <= 0) throw new InvalidArgumentException('Price must be positive.');
            return ["Add service \"{$p['name']}\" ({$p['category']}) at " . rm($price) . ", {$p['labour_hours']}h labour",
                    ['name' => $p['name'], 'category' => $p['category'], 'price' => $price, 'labour_hours' => (float)$p['labour_hours']]];

        case 'job_posting':
            return ["Open job posting: {$p['title']} ({$p['employment_type']}, {$p['pay_range']})",
                    ['title' => $p['title'], 'employment_type' => $p['employment_type'], 'pay_range' => $p['pay_range'], 'description' => $p['description']]];

        case 'pay_change':
            $e = row('SELECT id, name, hourly_rate FROM employees WHERE id = ?', [(int)$p['employee_id']]);
            if (!$e) throw new InvalidArgumentException("No employee with id {$p['employee_id']}.");
            $new = round((float)$p['new_hourly_rate'], 2);
            if ($new <= 0) throw new InvalidArgumentException('Rate must be positive.');
            return ["Change {$e['name']}'s hourly rate: " . rm($e['hourly_rate']) . ' → ' . rm($new),
                    ['employee_id' => (int)$e['id'], 'new_hourly_rate' => $new]];
    }
    throw new InvalidArgumentException("Unknown action type $type");
}

/** Store a proposal; run it straight away if on autopilot. Returns [id, status, message]. */
function propose_action(string $dept, string $type, array $input, string $reason, ?int $conversationId): array
{
    [$summary, $payload] = describe_action($type, $input);
    q('INSERT INTO ai_actions (department, type, summary, reason, payload, conversation_id) VALUES (?, ?, ?, ?, ?, ?)',
      [$dept, $type, $summary, $reason, json_encode($payload, JSON_UNESCAPED_UNICODE), $conversationId]);
    $id = insert_id();

    if (autopilot_on($type)) {
        $res = execute_action($id, 'autopilot');
        return [$id, $res['status'], "Action #$id ran on autopilot: {$res['result']}"];
    }
    return [$id, 'pending', "Action #$id submitted to the owner's approval queue: $summary. It has NOT happened yet."];
}

/** Carry out an approved action. */
function execute_action(int $id, string $by): array
{
    $a = row('SELECT * FROM ai_actions WHERE id = ?', [$id]);
    if (!$a || $a['status'] !== 'pending') {
        return ['status' => $a['status'] ?? 'missing', 'result' => 'Not pending'];
    }
    $p = json_decode($a['payload'], true);
    try {
        switch ($a['type']) {
            case 'restock':
                $cost = (float)val('SELECT cost FROM parts WHERE id = ?', [$p['part_id']]);
                q('INSERT INTO purchase_orders (part_id, qty, unit_cost) VALUES (?, ?, ?)', [$p['part_id'], $p['quantity'], $cost]);
                $result = 'Purchase order #' . insert_id() . ' created. Mark it received under Parts & stock when it arrives.';
                break;
            case 'price_change':
                $table = $p['item_type'] === 'part' ? 'parts' : 'services';
                q("UPDATE $table SET price = ? WHERE id = ?", [$p['new_price'], $p['item_id']]);
                $result = 'Price updated.';
                break;
            case 'social_post':
                q('INSERT INTO marketing_posts (channel, caption, video_idea, scheduled_for) VALUES (?, ?, ?, ?)',
                  [$p['channel'], $p['caption'], $p['video_idea'], $p['scheduled_for']]);
                $result = 'Post #' . insert_id() . ' added to the content calendar.';
                break;
            case 'promotion':
                q('INSERT INTO promotions (title, description, service_id, discount_percent, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?)',
                  [$p['title'], $p['description'], $p['service_id'], $p['discount_percent'], $p['start_date'], $p['end_date']]);
                $result = 'Promotion #' . insert_id() . ' is active.';
                break;
            case 'new_service':
                q('INSERT INTO services (name, category, price, labour_hours) VALUES (?, ?, ?, ?)',
                  [$p['name'], $p['category'], $p['price'], $p['labour_hours']]);
                $result = 'Service #' . insert_id() . ' added to the menu.';
                break;
            case 'job_posting':
                q('INSERT INTO job_postings (title, employment_type, pay_range, description) VALUES (?, ?, ?, ?)',
                  [$p['title'], $p['employment_type'], $p['pay_range'], $p['description']]);
                $result = 'Job posting #' . insert_id() . ' opened.';
                break;
            case 'pay_change':
                q('UPDATE employees SET hourly_rate = ? WHERE id = ?', [$p['new_hourly_rate'], $p['employee_id']]);
                $result = 'Hourly rate updated.';
                break;
            default:
                throw new RuntimeException('Unknown action type');
        }
        $status = 'executed';
    } catch (Throwable $e) {
        $status = 'failed';
        $result = $e->getMessage();
    }
    q('UPDATE ai_actions SET status = ?, result = ?, decided_at = NOW(), decided_by = ? WHERE id = ?', [$status, $result, $by, $id]);
    return ['status' => $status, 'result' => $result];
}

function reject_action(int $id, string $by): void
{
    q("UPDATE ai_actions SET status = 'rejected', decided_at = NOW(), decided_by = ? WHERE id = ? AND status = 'pending'", [$by, $id]);
}
