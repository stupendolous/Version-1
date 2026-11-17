<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../vendor/autoload.php';

function h(mixed $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** Render AI-written Markdown safely (raw HTML is escaped). */
function md(?string $text): string
{
    static $p = null;
    if (!$p) {
        $p = new Parsedown();
        $p->setSafeMode(true);
        $p->setBreaksEnabled(true);
    }
    return '<div class="md">' . $p->text((string)$text) . '</div>';
}

function badge(string $status): string
{
    $map = [
        'pending' => 'warn', 'open' => 'info', 'in_progress' => 'info', 'todo' => 'muted', 'doing' => 'info',
        'running' => 'info', 'scheduled' => 'info', 'ordered' => 'info', 'active' => 'good',
        'executed' => 'good', 'completed' => 'good', 'done' => 'good', 'received' => 'good', 'posted' => 'good', 'filled' => 'good',
        'rejected' => 'muted', 'cancelled' => 'muted', 'closed' => 'muted', 'inactive' => 'muted',
        'failed' => 'bad', 'high' => 'bad', 'medium' => 'warn', 'low' => 'muted',
    ];
    return '<span class="badge ' . ($map[$status] ?? 'muted') . '">' . h(str_replace('_', ' ', $status)) . '</span>';
}

function icon(string $name): string
{
    $paths = [
        'home' => 'M3 10.5 12 3l9 7.5V21h-6v-6H9v6H3z',
        'ceo' => 'M12 2l3 6 6 .9-4.5 4.3 1 6.3L12 16.6 6.5 19.5l1-6.3L3 8.9 9 8z',
        'marketing' => 'M3 11v2l13 5V6L3 11zm13-5 5-2v16l-5-2',
        'rnd' => 'M9 3h6M10 3v6l-5 9a2 2 0 0 0 2 3h10a2 2 0 0 0 2-3l-5-9V3',
        'hr' => 'M16 11a4 4 0 1 0-8 0 4 4 0 0 0 8 0zM4 21c0-4 4-6 8-6s8 2 8 6',
        'analytics' => 'M4 20V10M10 20V4M16 20v-7M22 20H2',
        'approvals' => 'M9 12l2 2 4-4M12 22a10 10 0 1 1 0-20 10 10 0 0 1 0 20z',
        'tasks' => 'M9 6h11M9 12h11M9 18h11M4 6h.01M4 12h.01M4 18h.01',
        'jobs' => 'M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.5-.5-.5-2.5z',
        'customers' => 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M23 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8',
        'vehicles' => 'M5 17h14M3 13l2-6h14l2 6v5H3zM7 17v2M17 17v2',
        'services' => 'M12 8v8M8 12h8M3 12a9 9 0 1 0 18 0 9 9 0 0 0-18 0',
        'parts' => 'M21 16V8l-9-5-9 5v8l9 5 9-5zM3.3 7 12 12l8.7-5M12 22V12',
        'staff' => 'M20 7H4v13h16zM16 7V4H8v3',
        'expenses' => 'M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6',
        'settings' => 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-2.9 1.2V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-2.9-1.2l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1A1.7 1.7 0 0 0 3 15.4H3a2 2 0 1 1 0-4h.1A1.7 1.7 0 0 0 4.6 9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1A1.7 1.7 0 0 0 9.4 4.6V4.5a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 2.9 1.2l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0 1.2 2.9h.1a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.9.5z',
    ];
    return '<svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><path d="' . ($paths[$name] ?? $paths['home']) . '"/></svg>';
}

function page_header(string $title, string $active = ''): void
{
    $user = current_user();
    $pending = $user ? (int)val("SELECT COUNT(*) FROM ai_actions WHERE status = 'pending'") : 0;
    $name = defined('COMPANY_NAME') ? COMPANY_NAME : 'STRGarage';
    $nav = [
        'AI Company' => [
            ['index.php', 'home', 'Dashboard'],
            ['department.php?d=ceo', 'ceo', 'AI CEO'],
            ['department.php?d=marketing', 'marketing', 'Marketing'],
            ['department.php?d=rnd', 'rnd', 'R&D'],
            ['department.php?d=hr', 'hr', 'HR'],
            ['department.php?d=analytics', 'analytics', 'Analytics'],
            ['approvals.php', 'approvals', 'Approvals'],
            ['tasks.php', 'tasks', 'Task board'],
        ],
        'Garage' => [
            ['jobs.php', 'jobs', 'Work orders'],
            ['data.php?t=customers', 'customers', 'Customers'],
            ['data.php?t=vehicles', 'vehicles', 'Vehicles'],
            ['data.php?t=services', 'services', 'Services'],
            ['data.php?t=parts', 'parts', 'Parts & stock'],
            ['data.php?t=employees', 'staff', 'Staff'],
            ['data.php?t=expenses', 'expenses', 'Expenses'],
            ['settings.php', 'settings', 'Settings'],
        ],
    ];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?> · <?= h($name) ?></title>
<link rel="stylesheet" href="assets/app.css?v=2">
<script>window.CSRF = <?= json_encode(csrf_token()) ?>;</script>
</head>
<body>
<?php if ($user): ?>
<aside class="side" id="side">
  <div class="brand"><span class="logo">STR</span><div><b><?= h($name) ?></b><small>AI-run workshop</small></div></div>
  <?php foreach ($nav as $group => $items): ?>
    <div class="nav-group"><?= h($group) ?></div>
    <?php foreach ($items as [$href, $ic, $label]): ?>
      <a class="nav<?= $active === $ic ? ' on' : '' ?>" href="<?= h($href) ?>"><?= icon($ic) ?><span><?= h($label) ?></span>
        <?php if ($ic === 'approvals' && $pending): ?><em class="count"><?= $pending ?></em><?php endif; ?></a>
    <?php endforeach; ?>
  <?php endforeach; ?>
  <div class="side-foot"><?= h($user['name']) ?> · <a href="logout.php">Log out</a></div>
</aside>
<?php endif; ?>
<main class="<?= $user ? 'with-side' : 'bare' ?>">
<?php if ($user): ?>
  <header class="top"><button class="menu" onclick="document.getElementById('side').classList.toggle('open')" aria-label="Menu">☰</button><h1><?= h($title) ?></h1></header>
<?php endif; ?>
<?php if ($f = flash()): ?><div class="flash <?= h($f[0]) ?>"><?= h($f[1]) ?></div><?php endif; ?>
<?php
}

function page_footer(string $extraScripts = ''): void
{
    ?>
</main>
<script src="assets/app.js?v=2"></script>
<?= $extraScripts ?>
</body>
</html>
<?php
}
