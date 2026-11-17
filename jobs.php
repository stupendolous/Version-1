<?php
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/business.php';
require_login();

$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['act'] ?? '';
    if ($act === 'create') {
        $cid = (int)($_POST['customer_id'] ?? 0);
        if (!$cid) {
            if (trim($_POST['new_name'] ?? '') === '') { flash('Choose a customer or enter a new one.', 'bad'); redirect('jobs.php?new=1'); }
            q('INSERT INTO customers (name, phone, source) VALUES (?, ?, ?)', [trim($_POST['new_name']), trim($_POST['new_phone'] ?? ''), $_POST['new_source'] ?? 'walk-in']);
            $cid = insert_id();
        }
        $vid = (int)($_POST['vehicle_id'] ?? 0) ?: null;
        if (!$vid && trim($_POST['plate'] ?? '') !== '') {
            q('INSERT INTO vehicles (customer_id, plate, make, model, year, mileage) VALUES (?, ?, ?, ?, ?, ?)',
              [$cid, strtoupper(trim($_POST['plate'])), trim($_POST['make'] ?? ''), trim($_POST['model'] ?? ''), ($_POST['year'] ?? '') ?: null, ($_POST['mileage'] ?? '') ?: null]);
            $vid = insert_id();
        }
        q('INSERT INTO jobs (customer_id, vehicle_id, employee_id, complaint) VALUES (?, ?, ?, ?)',
          [$cid, $vid, ((int)($_POST['employee_id'] ?? 0)) ?: null, trim($_POST['complaint'] ?? '')]);
        redirect('jobs.php?id=' . insert_id());
    }
    $job = row('SELECT * FROM jobs WHERE id = ?', [(int)$_POST['job_id']]);
    if (!$job) redirect('jobs.php');
    $jid = (int)$job['id'];
    $locked = in_array($job['status'], ['completed', 'cancelled'], true);

    if ($act === 'add_item' && !$locked) {
        [$type, $ref] = explode(':', $_POST['item'] ?? ':') + [null, null];
        $qty = max(0.01, (float)($_POST['qty'] ?? 1));
        if ($type === 'service' && ($s = row('SELECT * FROM services WHERE id = ?', [(int)$ref]))) {
            q("INSERT INTO job_items (job_id, item_type, ref_id, description, qty, unit_price) VALUES (?, 'service', ?, ?, ?, ?)", [$jid, (int)$s['id'], $s['name'], $qty, (float)$s['price']]);
        } elseif ($type === 'part' && ($p = row('SELECT * FROM parts WHERE id = ?', [(int)$ref]))) {
            q("INSERT INTO job_items (job_id, item_type, ref_id, description, qty, unit_price, unit_cost) VALUES (?, 'part', ?, ?, ?, ?, ?)", [$jid, (int)$p['id'], $p['name'], $qty, (float)$p['price'], (float)$p['cost']]);
        }
        recalc_job($jid);
    } elseif ($act === 'remove_item' && !$locked) {
        q('DELETE FROM job_items WHERE id = ? AND job_id = ?', [(int)$_POST['item_id'], $jid]);
        recalc_job($jid);
    } elseif ($act === 'status' && !$locked) {
        $st = $_POST['status'];
        if ($st === 'completed') {
            // Take used parts out of stock.
            foreach (rows("SELECT ref_id, qty FROM job_items WHERE job_id = ? AND item_type = 'part'", [$jid]) as $it) {
                q('UPDATE parts SET qty = qty - ? WHERE id = ?', [(int)ceil((float)$it['qty']), (int)$it['ref_id']]);
            }
            q("UPDATE jobs SET status = 'completed', closed_at = NOW() WHERE id = ?", [$jid]);
            flash('Job completed. Parts taken out of stock.');
        } elseif (in_array($st, ['open', 'in_progress', 'cancelled'], true)) {
            q('UPDATE jobs SET status = ?, closed_at = ' . ($st === 'cancelled' ? 'NOW()' : 'NULL') . ' WHERE id = ?', [$st, $jid]);
        }
    } elseif ($act === 'details') {
        q('UPDATE jobs SET employee_id = ?, complaint = ?, notes = ? WHERE id = ?', [((int)$_POST['employee_id']) ?: null, $_POST['complaint'] ?? '', $_POST['notes'] ?? '', $jid]);
        flash('Saved.');
    }
    redirect("jobs.php?id=$jid");
}

$mechanics = rows("SELECT id, name FROM employees WHERE status = 'active' ORDER BY name");

// ---------- Job detail ----------
if ($id) {
    $job = row('SELECT j.*, c.name customer, c.phone, v.plate, v.make, v.model, v.year, v.mileage FROM jobs j
                JOIN customers c ON c.id = j.customer_id LEFT JOIN vehicles v ON v.id = j.vehicle_id WHERE j.id = ?', [$id]);
    if (!$job) redirect('jobs.php');
    $items = rows('SELECT * FROM job_items WHERE job_id = ? ORDER BY item_type DESC, id', [$id]);
    $services = rows('SELECT id, name, price FROM services WHERE active = 1 ORDER BY category, name');
    $parts = rows('SELECT id, name, price, qty FROM parts ORDER BY name');
    $locked = in_array($job['status'], ['completed', 'cancelled'], true);
    $cost = array_sum(array_map(fn($i) => $i['qty'] * $i['unit_cost'], $items));
    page_header("Work order #$id", 'jobs');
    ?>
    <p><a href="jobs.php">← All work orders</a></p>
    <div class="grid2">
      <section class="card">
        <div class="row-between"><h3><?= h($job['customer']) ?></h3><?= badge($job['status']) ?></div>
        <p><?= h(trim("{$job['plate']} · {$job['make']} {$job['model']} {$job['year']}", ' ·')) ?><?= $job['mileage'] ? ' · ' . number_format($job['mileage']) . ' km' : '' ?><br>
           <span class="muted small"><?= h($job['phone']) ?> · opened <?= h($job['opened_at']) ?><?= $job['closed_at'] ? ' · closed ' . h($job['closed_at']) : '' ?></span></p>
        <form method="post" class="form-grid">
          <?= csrf_field() ?><input type="hidden" name="job_id" value="<?= $id ?>"><input type="hidden" name="act" value="details">
          <label>Mechanic<select name="employee_id"><option value="">–</option><?php foreach ($mechanics as $m): ?><option value="<?= (int)$m['id'] ?>" <?= $m['id'] == $job['employee_id'] ? 'selected' : '' ?>><?= h($m['name']) ?></option><?php endforeach; ?></select></label>
          <label class="span2">Complaint<input name="complaint" value="<?= h($job['complaint']) ?>"></label>
          <label class="span2">Notes<textarea name="notes" rows="2"><?= h($job['notes']) ?></textarea></label>
          <div><button class="btn small">Save</button></div>
        </form>
        <?php if (!$locked): ?>
          <form method="post" class="inline status-btns"><?= csrf_field() ?><input type="hidden" name="job_id" value="<?= $id ?>"><input type="hidden" name="act" value="status">
            <?php if ($job['status'] === 'open'): ?><button name="status" value="in_progress" class="btn">Start work</button><?php endif; ?>
            <button name="status" value="completed" class="btn primary" onclick="return confirm('Complete this job and take its parts out of stock?')">Complete & invoice</button>
            <button name="status" value="cancelled" class="btn ghost" onclick="return confirm('Cancel this job?')">Cancel job</button>
          </form>
        <?php endif; ?>
      </section>

      <section class="card">
        <h3>Items</h3>
        <table class="tbl">
          <tr><th>Item</th><th class="num">Qty</th><th class="num">Price</th><th class="num">Amount</th><th></th></tr>
          <?php foreach ($items as $it): ?>
            <tr><td><?= h($it['description']) ?> <span class="muted small"><?= h($it['item_type']) ?></span></td><td class="num"><?= (float)$it['qty'] ?></td>
              <td class="num"><?= h(rm($it['unit_price'])) ?></td><td class="num"><?= h(rm($it['qty'] * $it['unit_price'])) ?></td>
              <td><?php if (!$locked): ?><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="job_id" value="<?= $id ?>"><input type="hidden" name="act" value="remove_item"><input type="hidden" name="item_id" value="<?= (int)$it['id'] ?>"><button class="btn small ghost" aria-label="Remove">✕</button></form><?php endif; ?></td></tr>
          <?php endforeach; ?>
          <tr class="total"><td colspan="3">Total</td><td class="num"><?= h(rm($job['total'])) ?></td><td></td></tr>
          <?php if ($cost > 0): ?><tr><td colspan="3" class="muted small">Parts cost / margin</td><td class="num muted small"><?= h(rm($cost)) ?> / <?= h(rm($job['total'] - $cost)) ?></td><td></td></tr><?php endif; ?>
        </table>
        <?php if (!$locked): ?>
          <form method="post" class="add-item"><?= csrf_field() ?><input type="hidden" name="job_id" value="<?= $id ?>"><input type="hidden" name="act" value="add_item">
            <select name="item" required>
              <option value="">Add service or part…</option>
              <optgroup label="Services"><?php foreach ($services as $s): ?><option value="service:<?= (int)$s['id'] ?>"><?= h($s['name']) ?> – <?= h(rm($s['price'])) ?></option><?php endforeach; ?></optgroup>
              <optgroup label="Parts"><?php foreach ($parts as $p): ?><option value="part:<?= (int)$p['id'] ?>"><?= h($p['name']) ?> – <?= h(rm($p['price'])) ?> (<?= (int)$p['qty'] ?> in stock)</option><?php endforeach; ?></optgroup>
            </select>
            <input type="number" name="qty" value="1" min="0.01" step="0.01" aria-label="Quantity">
            <button class="btn primary">Add</button>
          </form>
        <?php endif; ?>
      </section>
    </div>
    <?php
    page_footer();
    exit;
}

// ---------- New job ----------
if (isset($_GET['new'])) {
    $customers = rows('SELECT id, name, phone FROM customers ORDER BY name');
    $vehicles = rows('SELECT id, customer_id, plate, make, model FROM vehicles ORDER BY plate');
    page_header('New work order', 'jobs');
    ?>
    <p><a href="jobs.php">← All work orders</a></p>
    <form method="post" class="card form-grid" id="newJob">
      <?= csrf_field() ?><input type="hidden" name="act" value="create">
      <label class="span2">Existing customer
        <select name="customer_id" id="custSel"><option value="">– New customer –</option>
          <?php foreach ($customers as $c): ?><option value="<?= (int)$c['id'] ?>"><?= h($c['name']) ?> <?= h($c['phone']) ?></option><?php endforeach; ?></select></label>
      <fieldset class="span2 newcust"><legend>New customer</legend>
        <label>Name<input name="new_name"></label><label>Phone<input name="new_phone"></label>
        <label>Found us via<select name="new_source"><?php foreach (['tiktok', 'walk-in', 'referral', 'google', 'instagram', 'facebook'] as $s): ?><option><?= $s ?></option><?php endforeach; ?></select></label>
      </fieldset>
      <label class="span2">Vehicle
        <select name="vehicle_id" id="vehSel"><option value="">– New vehicle –</option>
          <?php foreach ($vehicles as $v): ?><option value="<?= (int)$v['id'] ?>" data-c="<?= (int)$v['customer_id'] ?>"><?= h("{$v['plate']} {$v['make']} {$v['model']}") ?></option><?php endforeach; ?></select></label>
      <fieldset class="span2 newveh"><legend>New vehicle</legend>
        <label>Plate<input name="plate"></label><label>Make<input name="make" placeholder="Perodua"></label><label>Model<input name="model" placeholder="Myvi"></label>
        <label>Year<input type="number" name="year" min="1970" max="2100"></label><label>Mileage (km)<input type="number" name="mileage" min="0"></label>
      </fieldset>
      <label>Mechanic<select name="employee_id"><option value="">–</option><?php foreach ($mechanics as $m): ?><option value="<?= (int)$m['id'] ?>"><?= h($m['name']) ?></option><?php endforeach; ?></select></label>
      <label class="span2">Complaint / request<input name="complaint" placeholder="e.g. Aircond tak sejuk"></label>
      <div><button class="btn primary">Create work order</button></div>
    </form>
    <?php
    page_footer('<script>initNewJob();</script>');
    exit;
}

// ---------- List ----------
$status = $_GET['status'] ?? '';
$search = trim($_GET['q'] ?? '');
$where = [];
$params = [];
if ($status === 'open') { $where[] = "j.status IN ('open','in_progress')"; }
elseif (in_array($status, ['completed', 'cancelled'], true)) { $where[] = 'j.status = ?'; $params[] = $status; }
if ($search !== '') { $where[] = '(c.name LIKE ? OR v.plate LIKE ? OR j.complaint LIKE ?)'; array_push($params, "%$search%", "%$search%", "%$search%"); }
$jobs = rows('SELECT j.*, c.name customer, v.plate, v.make, v.model, e.name mechanic FROM jobs j JOIN customers c ON c.id = j.customer_id
              LEFT JOIN vehicles v ON v.id = j.vehicle_id LEFT JOIN employees e ON e.id = j.employee_id '
              . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY j.id DESC LIMIT 200', $params);

page_header('Work orders', 'jobs');
?>
<div class="row-between toolbar">
  <div class="filters">
    <?php foreach (['' => 'All', 'open' => 'In workshop', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $k => $l): ?>
      <a class="chip <?= $status === $k ? 'on' : '' ?>" href="jobs.php?status=<?= $k ?>"><?= $l ?></a>
    <?php endforeach; ?>
  </div>
  <form class="inline"><input type="hidden" name="status" value="<?= h($status) ?>"><input name="q" value="<?= h($search) ?>" placeholder="Search name, plate…"></form>
  <a href="jobs.php?new=1" class="btn primary">+ New work order</a>
</div>
<section class="card">
  <table class="tbl click">
    <tr><th>#</th><th>Customer</th><th>Vehicle</th><th>Complaint</th><th>Mechanic</th><th>Status</th><th class="num">Total</th><th>Opened</th></tr>
    <?php foreach ($jobs as $j): ?>
      <tr onclick="location='jobs.php?id=<?= (int)$j['id'] ?>'"><td><a href="jobs.php?id=<?= (int)$j['id'] ?>"><?= (int)$j['id'] ?></a></td><td><?= h($j['customer']) ?></td>
        <td><?= h("{$j['plate']} {$j['make']} {$j['model']}") ?></td><td><?= h(mb_strimwidth((string)$j['complaint'], 0, 40, '…')) ?></td>
        <td><?= h($j['mechanic']) ?></td><td><?= badge($j['status']) ?></td><td class="num"><?= h(rm($j['total'])) ?></td><td class="muted small nowrap"><?= h(substr($j['opened_at'], 0, 16)) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$jobs): ?><tr><td colspan="8" class="muted">No work orders.</td></tr><?php endif; ?>
  </table>
</section>
<?php page_footer(); ?>
