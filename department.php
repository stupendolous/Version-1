<?php
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/ai/agent.php';
require_login();

$d = $_GET['d'] ?? 'ceo';
if (!isset(DEPARTMENTS[$d])) redirect('index.php');
$dept = DEPARTMENTS[$d];

$convId = isset($_GET['new']) ? null
    : ((int)($_GET['c'] ?? 0) ?: (int)val("SELECT id FROM ai_conversations WHERE department = ? AND kind = 'chat' ORDER BY id DESC LIMIT 1", [$d]));
if ($convId && val('SELECT department FROM ai_conversations WHERE id = ?', [$convId]) !== $d) $convId = null;
$history = $convId ? conversation_view($convId) : [];
$convs = rows('SELECT c.id, c.kind, c.created_at, (SELECT display_text FROM ai_messages m WHERE m.conversation_id = c.id ORDER BY id LIMIT 1) first
               FROM ai_conversations c WHERE department = ? ORDER BY id DESC LIMIT 12', [$d]);
$report = row('SELECT * FROM ai_reports WHERE department = ? ORDER BY id DESC LIMIT 1', [$d]);
$actions = rows('SELECT * FROM ai_actions WHERE department = ? ORDER BY id DESC LIMIT 8', [$d]);
$tasks = rows("SELECT * FROM ai_tasks WHERE department = ? AND status != 'done' ORDER BY FIELD(priority,'high','medium','low'), id DESC LIMIT 8", [$d]);

$suggest = [
    'ceo' => ['What should we focus on this week?', 'Where are we losing money?', 'Plan a promotion for next weekend with Marketing and HR'],
    'analytics' => ['Which services make the most profit?', 'Which customers haven\'t come back in 90 days?', 'Is TikTok actually bringing paying customers?'],
    'marketing' => ['Plan 5 TikToks for next week', 'Make a win-back campaign for lapsed customers', 'Ideas for our quietest day'],
    'rnd' => ['Design a service package for Myvi owners', 'What new service should we add?', 'How can we cut job time?'],
    'hr' => ['Are our mechanics overloaded?', 'Do we need to hire? Draft the ad', 'Create a training plan for the junior mechanic'],
][$d];

$toolLabels = ['query_database' => 'Queried database', 'get_kpis' => 'Checked KPIs', 'create_task' => 'Created task', 'delegate_to' => 'Delegated',
               'propose_restock' => 'Proposed restock', 'propose_price_change' => 'Proposed price change', 'draft_social_post' => 'Drafted post',
               'propose_promotion' => 'Proposed promotion', 'propose_new_service' => 'Proposed new service', 'propose_job_posting' => 'Proposed job posting',
               'propose_pay_change' => 'Proposed pay change'];

page_header($dept['name'], $d);
?>
<p class="muted lead"><?= h($dept['brief']) ?></p>
<div class="dept-layout">
  <section class="card chat" id="chat" data-dept="<?= h($d) ?>" data-conv="<?= (int)$convId ?>">
    <div class="row-between">
      <h3>Chat with <?= h($dept['name']) ?></h3>
      <div class="small"><a href="department.php?d=<?= h($d) ?>&new=1">New chat</a></div>
    </div>
    <div class="msgs" id="msgs">
      <?php if (!$history): ?>
        <div class="empty">Ask a question or give an order. <?= h($dept['name']) ?> can look at your real data, propose actions for your approval, and create tasks.</div>
      <?php endif; ?>
      <?php foreach ($history as $m): ?>
        <?php if ($m['type'] === 'user'): ?>
          <div class="msg user"><?= nl2br(h($m['text'])) ?></div>
        <?php elseif ($m['type'] === 'tool'): ?>
          <div class="tool">↳ <?= h($toolLabels[$m['name']] ?? $m['name']) ?><?= $m['name'] === 'delegate_to' ? ' to ' . h(DEPARTMENTS[$m['input']['department']]['name'] ?? '') : '' ?></div>
        <?php else: ?>
          <div class="msg ai"><?= md($m['text']) ?></div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
    <div class="chips"><?php foreach ($suggest as $s): ?><button type="button" class="chip" data-text="<?= h($s) ?>"><?= h($s) ?></button><?php endforeach; ?></div>
    <form id="chatForm" class="chat-form">
      <textarea name="message" rows="2" placeholder="Message <?= h($dept['name']) ?>… (Enter to send, Shift+Enter for a new line)" required <?= ai_configured() ? '' : 'disabled' ?>></textarea>
      <button class="btn primary" <?= ai_configured() ? '' : 'disabled' ?>>Send</button>
    </form>
    <?php if (!ai_configured()): ?><p class="flash bad small">Add ANTHROPIC_API_KEY to your .env file to talk to the AI departments.</p><?php endif; ?>
    <?php if ($convs): ?>
      <details class="history"><summary>Past conversations & cycle runs</summary>
        <?php foreach ($convs as $c): ?>
          <a href="department.php?d=<?= h($d) ?>&c=<?= (int)$c['id'] ?>" class="<?= $c['id'] == $convId ? 'on' : '' ?>">
            <?= badge($c['kind']) ?> <?= h(mb_strimwidth($c['kind'] === 'chat' ? ($c['first'] ?? '') : 'Weekly review', 0, 60, '…')) ?> <span class="muted"><?= h($c['created_at']) ?></span></a>
        <?php endforeach; ?>
      </details>
    <?php endif; ?>
  </section>

  <aside class="dept-side">
    <section class="card">
      <h3>Latest report</h3>
      <?php if ($report): ?><p class="muted small"><?= h($report['created_at']) ?></p><div class="scroll"><?= md($report['content']) ?></div>
      <?php else: ?><p class="muted">No report yet. Run a company cycle from the dashboard.</p><?php endif; ?>
    </section>

    <section class="card">
      <div class="row-between"><h3>Proposals</h3><a href="approvals.php" class="small">Approvals →</a></div>
      <?php if (!$actions): ?><p class="muted">None yet.</p><?php endif; ?>
      <?php foreach ($actions as $a): ?><div class="line"><?= badge($a['status']) ?> <?= h($a['summary']) ?></div><?php endforeach; ?>
    </section>

    <section class="card">
      <div class="row-between"><h3>Open tasks</h3><a href="tasks.php" class="small">Task board →</a></div>
      <?php if (!$tasks): ?><p class="muted">No open tasks.</p><?php endif; ?>
      <?php foreach ($tasks as $t): ?><div class="line"><?= badge($t['priority']) ?> <?= h($t['title']) ?><?= $t['due_on'] ? ' <span class="muted small">due ' . h($t['due_on']) . '</span>' : '' ?></div><?php endforeach; ?>
    </section>

    <?php if ($d === 'marketing'):
        $posts = rows("SELECT * FROM marketing_posts WHERE status = 'scheduled' ORDER BY scheduled_for IS NULL, scheduled_for LIMIT 8");
        $promos = rows("SELECT * FROM promotions WHERE status = 'active' AND (end_date IS NULL OR end_date >= CURDATE()) ORDER BY start_date"); ?>
      <section class="card"><h3>Content calendar</h3>
        <?php if (!$posts): ?><p class="muted">No scheduled posts.</p><?php endif; ?>
        <?php foreach ($posts as $p): ?>
          <details class="post"><summary><b><?= h($p['scheduled_for'] ?: 'Unscheduled') ?></b> · <?= h(ucfirst($p['channel'])) ?> · <?= h(mb_strimwidth($p['caption'], 0, 50, '…')) ?></summary>
            <p><b>Caption</b></p><pre class="copy"><?= h($p['caption']) ?></pre><p><b>Video idea</b></p><p><?= nl2br(h($p['video_idea'])) ?></p>
            <form method="post" action="data.php?t=marketing_posts" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="set_status" value="posted"><button class="btn small">Mark posted</button></form>
          </details>
        <?php endforeach; ?>
      </section>
      <section class="card"><h3>Active promotions</h3>
        <?php if (!$promos): ?><p class="muted">None.</p><?php endif; ?>
        <?php foreach ($promos as $p): ?><div class="line"><b><?= h($p['title']) ?></b> · <?= (float)$p['discount_percent'] ?>% off · <?= h($p['start_date']) ?> – <?= h($p['end_date']) ?></div><?php endforeach; ?>
      </section>
    <?php elseif ($d === 'hr'):
        $staff = rows("SELECT e.*, (SELECT COUNT(*) FROM jobs j WHERE j.employee_id = e.id AND j.status='completed' AND j.closed_at >= CURDATE() - INTERVAL 30 DAY) jobs30
                       FROM employees e WHERE status = 'active' ORDER BY jobs30 DESC");
        $posts = rows("SELECT * FROM job_postings WHERE status = 'open' ORDER BY id DESC"); ?>
      <section class="card"><h3>Team · last 30 days</h3>
        <table class="tbl"><tr><th>Name</th><th>Role</th><th class="num">Jobs</th><th class="num">Rate/h</th></tr>
        <?php foreach ($staff as $s): ?><tr><td><?= h($s['name']) ?></td><td><?= h($s['role']) ?></td><td class="num"><?= (int)$s['jobs30'] ?></td><td class="num"><?= h(rm($s['hourly_rate'])) ?></td></tr><?php endforeach; ?></table>
      </section>
      <section class="card"><h3>Open job postings</h3>
        <?php if (!$posts): ?><p class="muted">None.</p><?php endif; ?>
        <?php foreach ($posts as $p): ?><details class="post"><summary><b><?= h($p['title']) ?></b> · <?= h($p['employment_type']) ?> · <?= h($p['pay_range']) ?></summary><pre class="copy"><?= h($p['description']) ?></pre></details><?php endforeach; ?>
      </section>
    <?php elseif ($d === 'analytics'):
        $low = rows('SELECT * FROM parts WHERE qty <= reorder_level ORDER BY qty - reorder_level LIMIT 10'); ?>
      <section class="card"><h3>Low stock</h3>
        <?php if (!$low): ?><p class="muted">All parts above reorder level.</p><?php endif; ?>
        <table class="tbl"><?php foreach ($low as $p): ?><tr><td><?= h($p['name']) ?></td><td class="num"><?= (int)$p['qty'] ?> / <?= (int)$p['reorder_level'] ?></td></tr><?php endforeach; ?></table>
      </section>
    <?php elseif ($d === 'rnd'):
        $svc = rows('SELECT * FROM services WHERE active = 1 ORDER BY id DESC LIMIT 8'); ?>
      <section class="card"><h3>Service menu (newest first)</h3>
        <table class="tbl"><?php foreach ($svc as $s): ?><tr><td><?= h($s['name']) ?></td><td class="num"><?= h(rm($s['price'])) ?></td></tr><?php endforeach; ?></table>
        <a href="data.php?t=services" class="small">Full menu →</a>
      </section>
    <?php elseif ($d === 'ceo'):
        $cycles = rows('SELECT * FROM ai_cycles ORDER BY id DESC LIMIT 6'); ?>
      <section class="card"><h3>Company cycles</h3>
        <?php if (!$cycles): ?><p class="muted">None yet.</p><?php endif; ?>
        <?php foreach ($cycles as $c): ?><div class="line"><?= badge($c['status']) ?> #<?= (int)$c['id'] ?> <span class="muted small"><?= h($c['started_at']) ?></span><?= $c['directive'] ? '<br><span class="small">“' . h($c['directive']) . '”</span>' : '' ?></div><?php endforeach; ?>
      </section>
    <?php endif; ?>
  </aside>
</div>
<?php page_footer('<script>initChat(' . json_encode($toolLabels) . ');</script>'); ?>
