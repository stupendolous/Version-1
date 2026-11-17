<?php
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/ai/agent.php';
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $ids = isset($_POST['ids']) ? array_map('intval', (array)$_POST['ids']) : [(int)($_POST['id'] ?? 0)];
    $done = 0;
    $failed = [];
    foreach (array_filter($ids) as $id) {
        if (($_POST['do'] ?? '') === 'approve') {
            $r = execute_action($id, $user['name']);
            if ($r['status'] === 'failed') $failed[] = "#$id: {$r['result']}";
        } else {
            reject_action($id, $user['name']);
        }
        $done++;
    }
    if ($failed) {
        flash('Some actions failed - ' . implode('; ', $failed), 'bad');
    } elseif ($done) {
        flash((($_POST['do'] ?? '') === 'approve' ? 'Approved ' : 'Rejected ') . $done . ' action' . ($done > 1 ? 's' : '') . '.');
    }
    $back = $_POST['back'] ?? 'approvals.php';
    redirect(in_array($back, ['index.php', 'approvals.php'], true) ? $back : 'approvals.php');
}

$pending = rows("SELECT * FROM ai_actions WHERE status = 'pending' ORDER BY id DESC");
$history = rows("SELECT * FROM ai_actions WHERE status != 'pending' ORDER BY decided_at DESC, id DESC LIMIT 40");

page_header('Approvals', 'approvals');
?>
<p class="muted lead">The AI departments propose; you decide. Nothing below changes the business until you approve it. To let a type of action run without asking, turn on autopilot in <a href="settings.php">Settings</a>.</p>

<section class="card">
  <form method="post" id="bulk">
    <?= csrf_field() ?>
    <div class="row-between">
      <h3>Waiting (<?= count($pending) ?>)</h3>
      <?php if ($pending): ?><div><button name="do" value="approve" class="btn small primary">Approve selected</button> <button name="do" value="reject" class="btn small">Reject selected</button></div><?php endif; ?>
    </div>
    <?php if (!$pending): ?><p class="muted">Nothing waiting for you.</p><?php endif; ?>
    <?php foreach ($pending as $a): $p = json_decode($a['payload'], true); ?>
      <div class="approval">
        <label class="check"><input type="checkbox" name="ids[]" value="<?= (int)$a['id'] ?>"></label>
        <div class="body">
          <div><span class="dept"><?= h(DEPARTMENTS[$a['department']]['name'] ?? $a['department']) ?></span> <span class="muted small"><?= h(ACTION_TYPES[$a['type']]['label'] ?? $a['type']) ?> · #<?= (int)$a['id'] ?> · <?= h($a['created_at']) ?></span></div>
          <div class="summary"><?= h($a['summary']) ?></div>
          <?php if ($a['reason']): ?><div class="reason"><b>Why:</b> <?= h($a['reason']) ?></div><?php endif; ?>
          <?php if (in_array($a['type'], ['social_post', 'job_posting', 'promotion'], true)): ?>
            <details><summary class="small">Full content</summary><pre class="copy"><?= h($p['caption'] ?? $p['description'] ?? '') ?></pre>
              <?php if (!empty($p['video_idea'])): ?><p><b>Video idea:</b> <?= nl2br(h($p['video_idea'])) ?></p><?php endif; ?></details>
          <?php endif; ?>
        </div>
        <div class="btns">
          <button form="one<?= (int)$a['id'] ?>" name="do" value="approve" class="btn small primary">Approve</button>
          <button form="one<?= (int)$a['id'] ?>" name="do" value="reject" class="btn small">Reject</button>
        </div>
      </div>
    <?php endforeach; ?>
  </form>
  <?php foreach ($pending as $a): ?>
    <form method="post" id="one<?= (int)$a['id'] ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"></form>
  <?php endforeach; ?>
</section>

<section class="card">
  <h3>History</h3>
  <?php if (!$history): ?><p class="muted">No decisions yet.</p><?php endif; ?>
  <table class="tbl">
    <?php foreach ($history as $a): ?>
      <tr><td><?= badge($a['status']) ?></td><td><?= h(DEPARTMENTS[$a['department']]['name'] ?? $a['department']) ?></td>
          <td><?= h($a['summary']) ?><?= $a['result'] ? '<br><span class="muted small">' . h($a['result']) . '</span>' : '' ?></td>
          <td class="muted small nowrap"><?= h($a['decided_by']) ?><br><?= h($a['decided_at']) ?></td></tr>
    <?php endforeach; ?>
  </table>
</section>
<?php page_footer(); ?>
