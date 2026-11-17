<?php
require_once __DIR__ . '/ai/engine.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    set_time_limit(0); // a full cycle makes 5 model calls and can take a few minutes
    $directive = trim($_POST['directive'] ?? '');
    try {
        if (($_POST['action'] ?? '') === 'cycle') {
            run_cycle($connect, $directive);
        } elseif (isset(DEPARTMENTS[$_POST['action'] ?? ''])) {
            $dept = $_POST['action'];
            $inputs = [];
            if ($dept !== 'analytics') {
                foreach (latest_reports($connect) as $key => $row) {
                    if ($key !== $dept && ($dept === 'ceo' || $key !== 'ceo')) {
                        $inputs[$key] = $row['content'];
                    }
                }
            }
            run_department($connect, $dept, $inputs, $directive);
        }
        header('Location: ai.php');
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$reports = latest_reports($connect);
$cycle = mysqli_fetch_assoc(mysqli_query($connect, 'SELECT * FROM ai_cycles ORDER BY id DESC LIMIT 1'));
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $h(COMPANY_NAME) ?> AI Company</title>
<style>
  body { font-family: system-ui, sans-serif; margin: 0; background: #f4f4f7; color: #222; }
  header { background: #1f2937; color: #fff; padding: 16px 24px; }
  header a { color: #c4b5fd; }
  main { max-width: 1100px; margin: 0 auto; padding: 16px; }
  .panel { background: #fff; border-radius: 8px; padding: 16px; margin-bottom: 16px; box-shadow: 0 1px 2px rgba(0,0,0,.08); }
  .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 16px; }
  .report { white-space: pre-wrap; font-size: 14px; line-height: 1.5; max-height: 480px; overflow: auto; }
  .ceo { border-left: 4px solid #7c3aed; }
  .meta { color: #666; font-size: 12px; }
  textarea { width: 100%; box-sizing: border-box; min-height: 60px; }
  button { padding: 8px 12px; margin: 4px 4px 0 0; cursor: pointer; }
  .error { background: #fee2e2; color: #991b1b; }
</style>
</head>
<body>
<header>
  <h2 style="margin:0"><?= $h(COMPANY_NAME) ?> &mdash; AI Company Control Center</h2>
  <small>Marketing &middot; R&amp;D &middot; HR &middot; Analytics &middot; AI CEO &nbsp;|&nbsp; <a href="index.php">Inventory</a></small>
</header>
<main>
  <?php if ($error): ?><div class="panel error"><?= $h($error) ?></div><?php endif; ?>

  <form class="panel" method="post">
    <label><b>Owner directive</b> (optional) &mdash; e.g. "Prepare for school holidays", "Cut costs this month"</label>
    <textarea name="directive"></textarea>
    <button name="action" value="cycle"><b>Run full company cycle</b></button>
    <?php foreach (DEPARTMENTS as $key => $d): ?>
      <button name="action" value="<?= $h($key) ?>">Run <?= $h($d['name']) ?></button>
    <?php endforeach; ?>
    <?php if ($cycle): ?>
      <p class="meta">Last cycle #<?= $h($cycle['id']) ?>: <?= $h($cycle['status']) ?>, started <?= $h($cycle['started_at']) ?></p>
    <?php endif; ?>
  </form>

  <?php if (isset($reports['ceo'])): ?>
    <div class="panel ceo">
      <h3>AI CEO plan</h3>
      <p class="meta"><?= $h($reports['ceo']['created_at']) ?> &middot; Items under "Needs owner approval" are not done until you approve them.</p>
      <div class="report"><?= $h($reports['ceo']['content']) ?></div>
    </div>
  <?php endif; ?>

  <div class="grid">
    <?php foreach (DEPARTMENTS as $key => $d): if ($key === 'ceo') continue; ?>
      <div class="panel">
        <h3><?= $h($d['name']) ?></h3>
        <?php if (isset($reports[$key])): $r = $reports[$key]; ?>
          <p class="meta"><?= $h($r['created_at']) ?> &middot; <?= (int)$r['input_tokens'] ?> in / <?= (int)$r['output_tokens'] ?> out tokens</p>
          <div class="report"><?= $h($r['content']) ?></div>
        <?php else: ?>
          <p class="meta">No report yet. Run a cycle.</p>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</main>
</body>
</html>
