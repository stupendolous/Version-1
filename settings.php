<?php
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/ai/agent.php';
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (isset($_POST['profile'])) {
        set_setting('company_profile', trim($_POST['profile']) ?: DEFAULT_COMPANY_PROFILE);
        foreach (ACTION_TYPES as $type => $def) {
            if ($def['autopilot']) set_setting("autopilot_$type", isset($_POST['auto'][$type]) ? '1' : '0');
        }
        flash('Settings saved.');
    } elseif (isset($_POST['new_password'])) {
        $u = row('SELECT * FROM users WHERE id = ?', [$user['id']]);
        if (!password_verify($_POST['current_password'] ?? '', $u['password_hash'])) {
            flash('Current password is wrong.', 'bad');
        } elseif (strlen($_POST['new_password']) < 8) {
            flash('New password must be at least 8 characters.', 'bad');
        } else {
            q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($_POST['new_password'], PASSWORD_DEFAULT), $user['id']]);
            flash('Password changed.');
        }
    }
    redirect('settings.php');
}

$usage = row('SELECT COALESCE(SUM(input_tokens),0) i, COALESCE(SUM(output_tokens),0) o FROM ai_conversations WHERE created_at >= CURDATE() - INTERVAL 30 DAY');
page_header('Settings', 'settings');
?>
<form method="post" class="card">
  <?= csrf_field() ?>
  <h3>Company profile</h3>
  <p class="muted small">Every AI department reads this. Describe what you do, where, who your customers are, your prices and your tone. Paste your TikTok bio here.</p>
  <textarea name="profile" rows="9" class="wide"><?= h(company_profile()) ?></textarea>

  <h3>Autopilot</h3>
  <p class="muted small">Action types that are ticked run straight away without waiting in Approvals. Leave them off until you trust the results. Pay changes always need you.</p>
  <div class="toggles">
    <?php foreach (ACTION_TYPES as $type => $def): ?>
      <label class="check"><input type="checkbox" name="auto[<?= h($type) ?>]" <?= autopilot_on($type) ? 'checked' : '' ?> <?= $def['autopilot'] ? '' : 'disabled' ?>> <?= h($def['label']) ?><?= $def['autopilot'] ? '' : ' <span class="muted small">(always needs you)</span>' ?></label>
    <?php endforeach; ?>
  </div>
  <p><button class="btn primary">Save settings</button></p>
</form>

<div class="grid2">
  <section class="card">
    <h3>AI connection</h3>
    <p><?= ai_configured() ? badge('active') . ' Connected with model <code>' . h(AI_MODEL) . '</code>.' : badge('failed') . ' No API key. Add <code>ANTHROPIC_API_KEY=…</code> to the <code>.env</code> file and restart the server.' ?></p>
    <p class="muted small">Last 30 days: <?= number_format((int)$usage['i']) ?> input and <?= number_format((int)$usage['o']) ?> output tokens. See exact cost at console.anthropic.com.</p>
    <h3>Run it automatically</h3>
    <p class="muted small">To run a company cycle every morning at 7:40 on a Mac or Linux machine, run <code>crontab -e</code> and add:</p>
    <pre class="copy">40 7 * * * cd <?= h(realpath(__DIR__)) ?> && <?= h(PHP_BINARY) ?> bin/run_cycle.php >> storage/cycle.log 2>&1</pre>
  </section>
  <form method="post" class="card">
    <?= csrf_field() ?>
    <h3>Change password</h3>
    <label>Current password<input type="password" name="current_password" required autocomplete="current-password"></label>
    <label>New password<input type="password" name="new_password" required minlength="8" autocomplete="new-password"></label>
    <p><button class="btn">Change password</button></p>
  </form>
</div>
<?php page_footer(); ?>
