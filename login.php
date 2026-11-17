<?php
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/ai/company.php';

$firstRun = (int)val('SELECT COUNT(*) FROM users') === 0;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass = (string)($_POST['password'] ?? '');
    if ($firstRun) {
        $name = trim($_POST['name'] ?? '');
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 8) {
            $error = 'Enter your name, a valid email and a password of at least 8 characters.';
        } else {
            q("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'owner')", [$name, $email, password_hash($pass, PASSWORD_DEFAULT)]);
            $_SESSION['user'] = ['id' => insert_id(), 'name' => $name, 'email' => $email];
            session_regenerate_id(true);
            redirect('index.php');
        }
    } else {
        $u = row('SELECT * FROM users WHERE email = ?', [$email]);
        if ($u && password_verify($pass, $u['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user'] = ['id' => (int)$u['id'], 'name' => $u['name'], 'email' => $u['email']];
            redirect('index.php');
        }
        $error = 'Wrong email or password.';
    }
}

page_header($firstRun ? 'Create owner account' : 'Log in');
?>
<div class="login">
  <div class="brand big"><span class="logo">STR</span><div><b><?= h(COMPANY_NAME) ?></b><small>AI-run workshop</small></div></div>
  <form method="post" class="card">
    <?= csrf_field() ?>
    <h2><?= $firstRun ? 'Create the owner account' : 'Log in' ?></h2>
    <?php if ($firstRun): ?><p class="muted">First time here. This account will approve everything the AI departments propose.</p><?php endif; ?>
    <?php if ($error): ?><div class="flash bad"><?= h($error) ?></div><?php endif; ?>
    <?php if ($firstRun): ?><label>Your name<input name="name" required autocomplete="name"></label><?php endif; ?>
    <label>Email<input type="email" name="email" required autocomplete="email" value="<?= h($_POST['email'] ?? '') ?>"></label>
    <label>Password<input type="password" name="password" required minlength="<?= $firstRun ? 8 : 1 ?>" autocomplete="<?= $firstRun ? 'new-password' : 'current-password' ?>"></label>
    <button class="btn primary wide"><?= $firstRun ? 'Create account' : 'Log in' ?></button>
  </form>
</div>
<?php page_footer(); ?>
