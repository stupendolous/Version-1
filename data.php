<?php
/** List / add / edit / delete for the garage's simple tables. */
require_once __DIR__ . '/lib/layout.php';
require_login();

$sources = ['tiktok', 'walk-in', 'referral', 'google', 'instagram', 'facebook', 'other'];
$E = [
    'customers' => ['title' => 'Customers', 'icon' => 'customers', 'order' => 'id DESC', 'search' => ['name', 'phone', 'email'],
        'fields' => ['name' => ['Name', 'text', true], 'phone' => ['Phone', 'text'], 'email' => ['Email', 'email'],
                     'source' => ['Found us via', 'select', false, $sources], 'notes' => ['Notes', 'textarea']],
        'list' => ['name', 'phone', 'source', 'created_at'],
        'extra' => '(SELECT COUNT(*) FROM jobs j WHERE j.customer_id = t.id AND j.status = \'completed\') AS visits, (SELECT MAX(closed_at) FROM jobs j WHERE j.customer_id = t.id) AS last_visit',
        'extra_cols' => ['visits' => 'Visits', 'last_visit' => 'Last visit']],
    'vehicles' => ['title' => 'Vehicles', 'icon' => 'vehicles', 'order' => 'id DESC', 'search' => ['plate', 'make', 'model'],
        'fields' => ['customer_id' => ['Owner', 'ref', true, 'SELECT id, name AS label FROM customers ORDER BY name'],
                     'plate' => ['Plate', 'text', true], 'make' => ['Make', 'text'], 'model' => ['Model', 'text'],
                     'year' => ['Year', 'number'], 'mileage' => ['Mileage (km)', 'number']],
        'list' => ['plate', 'make', 'model', 'year', 'customer_id', 'mileage']],
    'services' => ['title' => 'Services', 'icon' => 'services', 'order' => 'category, name', 'search' => ['name', 'category'],
        'fields' => ['name' => ['Name', 'text', true], 'category' => ['Category', 'text', true], 'price' => ['Price (RM)', 'money', true],
                     'labour_hours' => ['Labour hours', 'decimal', true], 'active' => ['Active', 'select', true, ['1' => 'Yes', '0' => 'No']]],
        'list' => ['name', 'category', 'price', 'labour_hours', 'active']],
    'parts' => ['title' => 'Parts & stock', 'icon' => 'parts', 'order' => 'name', 'search' => ['sku', 'name', 'category', 'supplier'],
        'fields' => ['sku' => ['SKU', 'text', true], 'name' => ['Name', 'text', true], 'category' => ['Category', 'text'],
                     'cost' => ['Cost (RM)', 'money', true], 'price' => ['Selling price (RM)', 'money', true], 'qty' => ['In stock', 'number', true],
                     'reorder_level' => ['Reorder at', 'number', true], 'supplier' => ['Supplier', 'text']],
        'list' => ['sku', 'name', 'category', 'cost', 'price', 'qty', 'reorder_level', 'supplier']],
    'employees' => ['title' => 'Staff', 'icon' => 'staff', 'order' => "status = 'active' DESC, name", 'search' => ['name', 'role'],
        'fields' => ['name' => ['Name', 'text', true], 'role' => ['Role', 'text', true], 'phone' => ['Phone', 'text'],
                     'hourly_rate' => ['Hourly rate (RM)', 'money', true], 'hired_on' => ['Hired on', 'date'],
                     'status' => ['Status', 'select', true, ['active' => 'Active', 'inactive' => 'Inactive']]],
        'list' => ['name', 'role', 'phone', 'hourly_rate', 'hired_on', 'status']],
    'expenses' => ['title' => 'Expenses', 'icon' => 'expenses', 'order' => 'spent_on DESC, id DESC', 'search' => ['category', 'description'],
        'fields' => ['spent_on' => ['Date', 'date', true], 'category' => ['Category', 'select', true, ['rent', 'utilities', 'salaries', 'parts', 'marketing', 'equipment', 'other']],
                     'amount' => ['Amount (RM)', 'money', true], 'description' => ['Description', 'text']],
        'list' => ['spent_on', 'category', 'amount', 'description']],
];

$t = $_GET['t'] ?? 'customers';

// Status updates coming from other pages.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $t === 'marketing_posts') {
    csrf_check();
    q('UPDATE marketing_posts SET status = ? WHERE id = ?', [in_array($_POST['set_status'], ['posted', 'cancelled'], true) ? $_POST['set_status'] : 'scheduled', (int)$_POST['id']]);
    redirect('department.php?d=marketing');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $t === 'purchase_orders') {
    csrf_check();
    $po = row("SELECT * FROM purchase_orders WHERE id = ? AND status = 'ordered'", [(int)$_POST['id']]);
    if ($po && $_POST['do'] === 'receive') {
        q('UPDATE parts SET qty = qty + ? WHERE id = ?', [(int)$po['qty'], (int)$po['part_id']]);
        q("UPDATE purchase_orders SET status = 'received', received_at = NOW() WHERE id = ?", [(int)$po['id']]);
        q("INSERT INTO expenses (category, amount, description, spent_on) VALUES ('parts', ?, ?, CURDATE())", [$po['qty'] * $po['unit_cost'], "Purchase order #{$po['id']}"]);
        flash("PO #{$po['id']} received: stock updated and expense recorded.");
    } elseif ($po) {
        q("UPDATE purchase_orders SET status = 'cancelled' WHERE id = ?", [(int)$po['id']]);
    }
    redirect('data.php?t=parts');
}

if (!isset($E[$t])) redirect('data.php?t=customers');
$cfg = $E[$t];
$fields = $cfg['fields'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    if (isset($_POST['delete'])) {
        try {
            q("DELETE FROM $t WHERE id = ?", [$id]);
            flash('Deleted.');
        } catch (mysqli_sql_exception $e) {
            flash('Could not delete: ' . $e->getMessage(), 'bad');
        }
        redirect("data.php?t=$t");
    }
    $vals = [];
    foreach ($fields as $f => [$label, $type]) {
        $v = trim((string)($_POST[$f] ?? ''));
        if (($fields[$f][2] ?? false) && $v === '') { flash("$label is required.", 'bad'); redirect("data.php?t=$t" . ($id ? "&edit=$id" : '&add=1')); }
        $vals[$f] = $v === '' ? null : (in_array($type, ['number', 'ref'], true) ? (int)$v : (in_array($type, ['money', 'decimal'], true) ? (float)$v : $v));
    }
    $cols = array_keys($vals);
    try {
        if ($id) {
            q("UPDATE $t SET " . implode(', ', array_map(fn($c) => "$c = ?", $cols)) . ' WHERE id = ?', [...array_values($vals), $id]);
        } else {
            q("INSERT INTO $t (" . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')', array_values($vals));
        }
        flash('Saved.');
    } catch (mysqli_sql_exception $e) {
        flash('Could not save: ' . $e->getMessage(), 'bad');
    }
    redirect("data.php?t=$t");
}

$refs = [];
foreach ($fields as $f => $def) {
    if ($def[1] === 'ref') {
        $refs[$f] = array_column(rows($def[3]), 'label', 'id');
    }
}

function field_input(string $f, array $def, mixed $value, array $refs): string
{
    [$label, $type] = $def;
    $req = ($def[2] ?? false) ? ' required' : '';
    $v = h($value ?? '');
    $in = match ($type) {
        'textarea' => "<textarea name=\"$f\" rows=\"2\"$req>$v</textarea>",
        'select' => "<select name=\"$f\"$req>" . implode('', array_map(
            fn($k, $l) => '<option value="' . h(array_is_list($def[3]) ? $l : $k) . '"' . ((string)$value === (string)(array_is_list($def[3]) ? $l : $k) ? ' selected' : '') . '>' . h($l) . '</option>',
            array_keys($def[3]), $def[3])) . '</select>',
        'ref' => "<select name=\"$f\"$req><option value=\"\">–</option>" . implode('', array_map(
            fn($k, $l) => '<option value="' . (int)$k . '"' . ((string)$value === (string)$k ? ' selected' : '') . '>' . h($l) . '</option>',
            array_keys($refs[$f]), $refs[$f])) . '</select>',
        'money', 'decimal' => "<input type=\"number\" step=\"0.01\" min=\"0\" name=\"$f\" value=\"$v\"$req>",
        'number' => "<input type=\"number\" step=\"1\" name=\"$f\" value=\"$v\"$req>",
        'date' => "<input type=\"date\" name=\"$f\" value=\"$v\"$req>",
        'email' => "<input type=\"email\" name=\"$f\" value=\"$v\"$req>",
        default => "<input name=\"$f\" value=\"$v\"$req>",
    };
    return '<label' . ($type === 'textarea' ? ' class="span2"' : '') . '>' . h($label) . $in . '</label>';
}

$editing = isset($_GET['edit']) ? row("SELECT * FROM $t WHERE id = ?", [(int)$_GET['edit']]) : null;
$adding = isset($_GET['add']);

$search = trim($_GET['q'] ?? '');
$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(' . implode(' OR ', array_map(fn($c) => "t.$c LIKE ?", $cfg['search'])) . ')';
    foreach ($cfg['search'] as $_) $params[] = "%$search%";
}
if ($t === 'parts' && isset($_GET['low'])) $where[] = 't.qty <= t.reorder_level';
$extra = isset($cfg['extra']) ? ', ' . $cfg['extra'] : '';
$list = rows("SELECT t.*$extra FROM $t t" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . " ORDER BY {$cfg['order']} LIMIT 300", $params);

page_header($cfg['title'], $cfg['icon']);
?>
<div class="row-between toolbar">
  <form class="inline"><input type="hidden" name="t" value="<?= h($t) ?>"><input name="q" value="<?= h($search) ?>" placeholder="Search…">
    <?php if ($t === 'parts'): ?><label class="check"><input type="checkbox" name="low" value="1" <?= isset($_GET['low']) ? 'checked' : '' ?> onchange="this.form.submit()"> Low stock only</label><?php endif; ?></form>
  <a href="data.php?t=<?= h($t) ?>&add=1" class="btn primary">+ Add</a>
</div>

<?php if ($editing || $adding): ?>
<section class="card">
  <h3><?= $editing ? 'Edit' : 'Add' ?></h3>
  <form method="post" class="form-grid">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">
    <?php foreach ($fields as $f => $def) echo field_input($f, $def, $editing[$f] ?? ($f === 'spent_on' ? date('Y-m-d') : null), $refs); ?>
    <div><button class="btn primary">Save</button> <a href="data.php?t=<?= h($t) ?>" class="btn ghost">Cancel</a></div>
  </form>
  <?php if ($editing): ?>
    <form method="post" class="inline" onsubmit="return confirm('Delete permanently?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$editing['id'] ?>"><button name="delete" value="1" class="btn small ghost">Delete</button></form>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($t === 'parts'):
    $pos = rows("SELECT po.*, p.name FROM purchase_orders po JOIN parts p ON p.id = po.part_id WHERE po.status = 'ordered' ORDER BY po.id DESC");
    if ($pos): ?>
  <section class="card">
    <h3>Purchase orders on the way</h3>
    <table class="tbl"><?php foreach ($pos as $po): ?>
      <tr><td>PO #<?= (int)$po['id'] ?></td><td><?= (int)$po['qty'] ?> × <?= h($po['name']) ?></td><td class="num"><?= h(rm($po['qty'] * $po['unit_cost'])) ?></td><td class="muted small"><?= h($po['created_at']) ?></td>
        <td><form method="post" action="data.php?t=purchase_orders" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$po['id'] ?>">
          <button name="do" value="receive" class="btn small primary">Mark received</button> <button name="do" value="cancel" class="btn small ghost">Cancel</button></form></td></tr>
    <?php endforeach; ?></table>
  </section>
<?php endif; endif; ?>

<section class="card">
  <table class="tbl click">
    <tr><?php foreach ($cfg['list'] as $c): ?><th class="<?= in_array($fields[$c][1] ?? '', ['money', 'number', 'decimal'], true) ? 'num' : '' ?>"><?= h($fields[$c][0] ?? ucfirst(str_replace('_', ' ', $c))) ?></th><?php endforeach; ?>
      <?php foreach ($cfg['extra_cols'] ?? [] as $l): ?><th><?= h($l) ?></th><?php endforeach; ?></tr>
    <?php foreach ($list as $r): ?>
      <tr onclick="location='data.php?t=<?= h($t) ?>&edit=<?= (int)$r['id'] ?>'" class="<?= $t === 'parts' && $r['qty'] <= $r['reorder_level'] ? 'warn-row' : '' ?>">
        <?php foreach ($cfg['list'] as $c):
            $type = $fields[$c][1] ?? 'text';
            $v = $r[$c];
            $out = match (true) {
                $type === 'money' => h(rm($v)),
                $type === 'ref' => h($refs[$c][$v] ?? ''),
                $type === 'select' && !array_is_list($fields[$c][3]) => h($fields[$c][3][$v] ?? $v),
                $c === 'created_at' => h(substr((string)$v, 0, 10)),
                default => h($v),
            }; ?>
          <td class="<?= in_array($type, ['money', 'number', 'decimal'], true) ? 'num' : '' ?>"><?= $out ?></td>
        <?php endforeach; ?>
        <?php foreach ($cfg['extra_cols'] ?? [] as $c => $l): ?><td><?= h(is_string($r[$c]) && strlen($r[$c]) > 10 ? substr($r[$c], 0, 10) : $r[$c]) ?></td><?php endforeach; ?>
      </tr>
    <?php endforeach; ?>
    <?php if (!$list): ?><tr><td colspan="9" class="muted">Nothing here yet.</td></tr><?php endif; ?>
  </table>
</section>
<?php page_footer(); ?>
