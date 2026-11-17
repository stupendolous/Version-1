<?php
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/ai/company.php';
$user = require_login();

$owners = ['owner' => 'Owner'] + array_map(fn($d) => $d['name'], DEPARTMENTS);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (isset($_POST['move'])) {
        q('UPDATE ai_tasks SET status = ? WHERE id = ?', [in_array($_POST['move'], ['todo', 'doing', 'done'], true) ? $_POST['move'] : 'todo', (int)$_POST['id']]);
    } elseif (isset($_POST['delete'])) {
        q('DELETE FROM ai_tasks WHERE id = ?', [(int)$_POST['id']]);
    } elseif (trim($_POST['title'] ?? '') !== '') {
        q("INSERT INTO ai_tasks (department, title, details, priority, due_on, created_by) VALUES (?, ?, ?, ?, ?, 'owner')",
          [isset($owners[$_POST['department']]) ? $_POST['department'] : 'owner', trim($_POST['title']), trim($_POST['details'] ?? ''),
           in_array($_POST['priority'] ?? '', ['low', 'medium', 'high'], true) ? $_POST['priority'] : 'medium', ($_POST['due_on'] ?? '') ?: null]);
        flash('Task added.');
    }
    redirect('tasks.php' . (isset($_GET['who']) ? '?who=' . urlencode($_GET['who']) : ''));
}

$who = $_GET['who'] ?? '';
$where = isset($owners[$who]) ? 'WHERE department = ?' : '';
$all = rows("SELECT * FROM ai_tasks $where ORDER BY FIELD(priority,'high','medium','low'), due_on IS NULL, due_on, id DESC", $where ? [$who] : []);
$cols = ['todo' => 'To do', 'doing' => 'In progress', 'done' => 'Done'];

page_header('Task board', 'tasks');
?>
<div class="filters">
  <a href="tasks.php" class="chip <?= $who === '' ? 'on' : '' ?>">Everyone</a>
  <?php foreach ($owners as $k => $n): ?><a href="tasks.php?who=<?= h($k) ?>" class="chip <?= $who === $k ? 'on' : '' ?>"><?= h($n) ?></a><?php endforeach; ?>
</div>

<div class="board">
  <?php foreach ($cols as $st => $label): $items = array_filter($all, fn($t) => $t['status'] === $st); ?>
    <section class="col">
      <h3><?= h($label) ?> <span class="muted">(<?= count($items) ?>)</span></h3>
      <?php foreach ($st === 'done' ? array_slice($items, 0, 15) : $items as $t): ?>
        <div class="task">
          <div class="row-between"><span class="dept"><?= h($owners[$t['department']] ?? $t['department']) ?></span><?= badge($t['priority']) ?></div>
          <b><?= h($t['title']) ?></b>
          <?php if ($t['details']): ?><details><summary class="small">Details</summary><div class="small"><?= nl2br(h($t['details'])) ?></div></details><?php endif; ?>
          <div class="muted small">by <?= h($owners[$t['created_by']] ?? $t['created_by']) ?><?= $t['due_on'] ? ' · due ' . h($t['due_on']) : '' ?></div>
          <form method="post" class="inline task-btns"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
            <?php foreach ($cols as $to => $tl): if ($to !== $st): ?><button name="move" value="<?= $to ?>" class="btn small">→ <?= h($tl) ?></button><?php endif; endforeach; ?>
            <button name="delete" value="1" class="btn small ghost" onclick="return confirm('Delete this task?')">Delete</button>
          </form>
        </div>
      <?php endforeach; ?>
    </section>
  <?php endforeach; ?>
</div>

<section class="card">
  <h3>Add a task</h3>
  <form method="post" class="form-grid">
    <?= csrf_field() ?>
    <label>Title<input name="title" required></label>
    <label>For<select name="department"><?php foreach ($owners as $k => $n): ?><option value="<?= h($k) ?>"><?= h($n) ?></option><?php endforeach; ?></select></label>
    <label>Priority<select name="priority"><option>medium</option><option>high</option><option>low</option></select></label>
    <label>Due<input type="date" name="due_on"></label>
    <label class="span2">Details<textarea name="details" rows="2"></textarea></label>
    <div><button class="btn primary">Add task</button></div>
  </form>
</section>
<?php page_footer(); ?>
