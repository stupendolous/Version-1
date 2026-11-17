<?php
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/ai/agent.php';
$user = require_login();

$k = business_kpis(30);
$weekly = revenue_by_week(12);
$cats = revenue_by_category(90);
$monthly = revenue_vs_expenses(6);
$cycle = row('SELECT * FROM ai_cycles ORDER BY id DESC LIMIT 1');
$ceo = row("SELECT * FROM ai_reports WHERE department = 'ceo' ORDER BY id DESC LIMIT 1");
$pending = rows("SELECT * FROM ai_actions WHERE status = 'pending' ORDER BY id DESC LIMIT 6");
$latest = [];
foreach (rows('SELECT r.* FROM ai_reports r JOIN (SELECT department, MAX(id) id FROM ai_reports GROUP BY department) m ON m.id = r.id') as $r) {
    $latest[$r['department']] = $r;
}
$hasData = (int)val('SELECT COUNT(*) FROM jobs') > 0;
$running = $cycle && $cycle['status'] === 'running';

$tile = function (string $label, string $value, ?string $sub = null, ?string $href = null) {
    $tag = $href ? 'a href="' . h($href) . '"' : 'div';
    echo "<$tag class=\"tile\"><span>" . h($label) . '</span><b>' . h($value) . '</b>' . ($sub ? '<small>' . $sub . '</small>' : '') . '</' . ($href ? 'a' : 'div') . '>';
};
$change = $k['revenue_change_pct'];
$changeHtml = $change === null ? 'no previous period' :
    '<span class="' . ($change >= 0 ? 'up' : 'down') . '">' . ($change >= 0 ? '▲ ' : '▼ ') . abs($change) . '%</span> vs previous 30 days';

page_header('Dashboard', 'home');
?>
<?php if (!ai_configured() || !$hasData): ?>
<section class="card setup">
  <h3>Getting started</h3>
  <ol>
    <li class="<?= ai_configured() ? 'done' : '' ?>">Add your Anthropic API key to the <code>.env</code> file (<code>ANTHROPIC_API_KEY=…</code>), then restart the server.</li>
    <li class="<?= $hasData ? 'done' : '' ?>">Add your real work orders under <a href="jobs.php">Work orders</a>, or load demo data with <code>php bin/install.php --demo</code>.</li>
    <li>Edit the company profile in <a href="settings.php">Settings</a> so every department knows your business.</li>
    <li>Click <b>Run company cycle</b> below.</li>
  </ol>
</section>
<?php endif; ?>

<section class="card cycle">
  <form method="post" action="api.php" id="cycleForm" class="cycle-form">
    <input type="hidden" name="action" value="start_cycle">
    <input name="directive" placeholder="Optional instruction for this cycle, e.g. &quot;Push aircond service before the hot season&quot;" <?= $running ? 'disabled' : '' ?>>
    <button class="btn primary" <?= ($running || !ai_configured()) ? 'disabled' : '' ?>>Run company cycle</button>
  </form>
  <p class="muted small" id="cycleStatus" data-running="<?= $running ? (int)$cycle['id'] : '' ?>">
    <?php if ($cycle): ?>
      Cycle #<?= (int)$cycle['id'] ?> · <?= badge($cycle['status']) ?> <?= h($cycle['progress'] ?? '') ?> · started <?= h($cycle['started_at']) ?>
    <?php else: ?>
      In a cycle, each department reviews the business, proposes actions and writes a report. Then the AI CEO turns everything into one plan.
    <?php endif; ?>
  </p>
</section>

<section class="tiles">
  <?php
  $tile('Revenue · 30 days', 'RM ' . number_format($k['revenue']), $changeHtml);
  $tile('Completed jobs', number_format($k['completed_jobs']), 'avg ticket RM ' . number_format($k['average_ticket']), 'jobs.php');
  $tile('Gross margin', $k['gross_margin_pct'] === null ? '–' : $k['gross_margin_pct'] . '%', 'after parts cost');
  $tile('Open jobs', (string)$k['open_jobs'], 'in the workshop now', 'jobs.php?status=open');
  $tile('Low stock', (string)$k['low_stock_parts'], 'parts at reorder level', 'data.php?t=parts&low=1');
  $tile('Awaiting approval', (string)$k['pending_approvals'], 'AI proposals', 'approvals.php');
  ?>
</section>

<section class="grid2">
  <div class="card">
    <h3>Weekly revenue</h3><p class="muted small">Completed jobs, last 12 full weeks (week starting)</p>
    <div class="chart"><canvas id="cWeekly" aria-label="Weekly revenue line chart"></canvas></div>
  </div>
  <div class="card">
    <h3>Revenue by category</h3><p class="muted small">Last 90 days</p>
    <div class="chart"><canvas id="cCats" aria-label="Revenue by category bar chart"></canvas></div>
  </div>
</section>

<section class="grid2">
  <div class="card">
    <div class="row-between"><h3>AI CEO plan</h3><a href="department.php?d=ceo" class="small">Talk to the CEO →</a></div>
    <?php if ($ceo): ?>
      <p class="muted small"><?= h($ceo['created_at']) ?></p>
      <div class="scroll"><?= md($ceo['content']) ?></div>
    <?php else: ?>
      <p class="muted">No plan yet. Run a company cycle.</p>
    <?php endif; ?>
  </div>
  <div class="card">
    <div class="row-between"><h3>Awaiting your approval</h3><a href="approvals.php" class="small">All approvals →</a></div>
    <?php if (!$pending): ?><p class="muted">Nothing waiting.</p><?php endif; ?>
    <?php foreach ($pending as $a): ?>
      <div class="action">
        <div><span class="dept"><?= h(DEPARTMENTS[$a['department']]['name'] ?? $a['department']) ?></span> <?= h($a['summary']) ?></div>
        <form method="post" action="approvals.php" class="inline">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><input type="hidden" name="back" value="index.php">
          <button name="do" value="approve" class="btn small primary">Approve</button>
          <button name="do" value="reject" class="btn small">Reject</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="card">
  <h3>Revenue vs expenses</h3><p class="muted small">By month</p>
  <div class="chart"><canvas id="cMonthly" aria-label="Monthly revenue and expenses bar chart"></canvas></div>
</section>

<section class="depts">
  <?php foreach (['analytics', 'marketing', 'rnd', 'hr'] as $d): $r = $latest[$d] ?? null; ?>
    <a class="card dept-card" href="department.php?d=<?= $d ?>">
      <div class="row-between"><h3><?= icon($d) ?> <?= h(DEPARTMENTS[$d]['name']) ?></h3><span class="small">Open →</span></div>
      <p class="muted small"><?= h(DEPARTMENTS[$d]['brief']) ?></p>
      <p class="snippet"><?= $r ? h(mb_strimwidth(preg_replace(['/^\s*\|?[\s:|-]+\|?\s*$/m', '/[#*_>`|]+/', '/\s+/'], ['', ' ', ' '], $r['content']), 0, 220, '…')) : 'No report yet.' ?></p>
    </a>
  <?php endforeach; ?>
</section>

<?php
$chartData = [
    'weekly' => ['labels' => array_map(fn($d) => date('j M', strtotime($d)), array_keys($weekly)), 'values' => array_values($weekly)],
    'cats' => ['labels' => array_column($cats, 'category'), 'values' => array_map('floatval', array_column($cats, 'revenue'))],
    'monthly' => ['labels' => array_map(fn($m) => date('M Y', strtotime("$m-01")) . ($m === date('Y-m') ? ' (so far)' : ''), array_keys($monthly)),
                  'revenue' => array_column(array_values($monthly), 'revenue'), 'expenses' => array_column(array_values($monthly), 'expenses')],
];
page_footer('<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>window.DASH = ' . json_encode($chartData) . '; drawDashboard();</script>');
