<?php
require_once __DIR__ . '/includes/functions.php';
$a = $_GET['a'] ?? 'login';
$redirectTo = safe_redirect_target($_GET['redirect_to'] ?? $_POST['redirect_to'] ?? url(''));
$err = ''; $ok = '';

if ($a === 'logout') {
    unset($_SESSION['uid']); session_regenerate_id(true);
    flash('You have been logged out.'); redirect(url(''));
}
if (current_user() && in_array($a, ['login', 'signup'], true)) redirect($redirectTo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (!empty($_POST['website'])) redirect(url(''));
    if ($a === 'login') {
        if (!throttle('login', 10, 900)) $err = 'Too many attempts. Please wait a few minutes and try again.';
        else {
            $id = trim($_POST['log'] ?? '');
            $st = db()->prepare('SELECT * FROM users WHERE username = ? OR email = ?');
            $st->execute([$id, $id]);
            $u = $st->fetch();
            if ($u && $u['status'] !== 'active') $err = 'This account has been suspended.';
            elseif ($u && password_verify($_POST['pwd'] ?? '', $u['pass_hash'])) {
                login_user($u);
                if (!empty($_POST['rememberme'])) {
                    $p = session_get_cookie_params();
                    setcookie(session_name(), session_id(), ['expires' => time() + 60 * 60 * 24 * 30, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
                }
                flash('Welcome back, ' . user_name($u) . '!');
                redirect($redirectTo);
            } else $err = 'Invalid username/email or password.';
        }
    } elseif ($a === 'signup') {
        $user = trim($_POST['user_name'] ?? ''); $email = trim($_POST['email'] ?? '');
        $p1 = $_POST['pass1'] ?? ''; $p2 = $_POST['pass2'] ?? '';
        if (!throttle('signup', 5, 3600)) $err = 'Too many sign ups from your network. Please try again later.';
        elseif (!preg_match('/^[A-Za-z0-9_.-]{3,30}$/', $user)) $err = 'Username must be 3–30 characters: letters, numbers, dot, dash or underscore.';
        elseif (strcasecmp($user, ADMIN_USER) === 0) $err = 'That username is not available.';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Please enter a valid email address.';
        elseif (strlen($p1) < 6) $err = 'Password must be at least 6 characters.';
        elseif ($p1 !== $p2) $err = 'Passwords do not match.';
        elseif (empty($_POST['agree'])) $err = 'Please agree to the Terms of Service and Privacy Policy.';
        else {
            $st = db()->prepare('SELECT username, email FROM users WHERE username = ? OR email = ?');
            $st->execute([$user, $email]);
            if ($row = $st->fetch()) $err = strcasecmp($row['username'], $user) === 0 ? 'That username is already taken.' : 'An account with that email already exists.';
            else {
                db()->prepare('INSERT INTO users (username, email, pass_hash, display_name) VALUES (?, ?, ?, ?)')
                    ->execute([$user, $email, password_hash($p1, PASSWORD_DEFAULT), $user]);
                $st = db()->prepare('SELECT * FROM users WHERE id = ?'); $st->execute([db()->lastInsertId()]);
                login_user($st->fetch());
                flash('Welcome to Imo Decides, ' . $user . '! Your account has been created.');
                redirect($redirectTo);
            }
        }
    } elseif ($a === 'lost') {
        $email = trim($_POST['user_mail'] ?? '');
        if (!throttle('lost', 5, 3600)) $err = 'Too many requests. Please try again later.';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Please enter a valid email address.';
        else {
            $st = db()->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'"); $st->execute([$email]);
            if ($u = $st->fetch()) {
                $token = bin2hex(random_bytes(24));
                db()->prepare('INSERT INTO password_resets (token_hash, user_id, expires_at) VALUES (?, ?, ?)')
                    ->execute([hash('sha256', $token), $u['id'], date('Y-m-d H:i:s', time() + 3600)]);
                $link = abs_url('reset-password/') . '?token=' . $token;
                send_mail($u['email'], 'Reset your Imo Decides password',
                    "Hello " . user_name($u) . ",\n\nSomeone asked to reset the password for your Imo Decides account.\nTo choose a new password, open this link (valid for 1 hour):\n\n$link\n\nIf you didn't ask for this, you can ignore this email.\n\nImo Decides");
            }
            $ok = 'If an account exists for that email, a link to create a new password has been sent. Check your inbox and spam folder.';
        }
    } elseif ($a === 'reset') {
        $th = hash('sha256', $_POST['token'] ?? '');
        $st = db()->prepare("SELECT * FROM password_resets WHERE token_hash = ? AND used = 0 AND expires_at > ?");
        $st->execute([$th, date('Y-m-d H:i:s')]);
        $r = $st->fetch();
        $p1 = $_POST['pass1'] ?? '';
        if (!$r) $err = 'This reset link is invalid or has expired. Please request a new one.';
        elseif (strlen($p1) < 6) $err = 'Password must be at least 6 characters.';
        elseif ($p1 !== ($_POST['pass2'] ?? '')) $err = 'Passwords do not match.';
        else {
            db()->prepare('UPDATE users SET pass_hash = ? WHERE id = ?')->execute([password_hash($p1, PASSWORD_DEFAULT), $r['user_id']]);
            db()->prepare('UPDATE password_resets SET used = 1 WHERE user_id = ?')->execute([$r['user_id']]);
            flash('Your password has been changed. You can sign in now.');
            redirect(url('log-in/'));
        }
    }
}

$titles = ['login' => 'Sign In', 'signup' => 'Sign Up', 'lost' => 'Forgot Password', 'reset' => 'Reset Password'];
$pageTitle = ($titles[$a] ?? 'Account') . ' – ' . SITE_NAME;
$panelImg = media_url($a === 'signup' ? 'signup.png' : 'signin.png');
require __DIR__ . '/includes/header.php';
$rt = '<input type="hidden" name="redirect_to" value="' . e($redirectTo) . '">';
?>
<div class="box">
  <div class="crumbs"><a href="<?= url('') ?>">Home</a> <span>/</span> <?= e($titles[$a] ?? 'Account') ?></div>
  <div class="panel">
    <div class="panel-side" <?php if ($panelImg): ?>style="background-image:url('<?= e($panelImg) ?>')"<?php endif; ?>>
      <?php if ($a === 'signup'): ?>
        <h3>Sign Up</h3><p>Sign Up to our social questions and Answers Engine to ask questions, answer people’s questions, and connect with other people.</p>
        <p class="panel-alt">Have an account? <a href="<?= url('log-in/') ?>">Sign In</a></p>
      <?php elseif ($a === 'login'): ?>
        <h3>Sign In</h3><p>Login to our social questions &amp; Answers Engine to ask questions answer people’s questions &amp; connect with other people.</p>
        <p class="panel-alt">Don't have account, <a href="<?= url('signup/') ?>">Sign Up Here</a></p>
      <?php else: ?>
        <h3>Forgot Password</h3><p>Lost your password? Please enter your email address. You will receive a link and will create a new password via email.</p>
      <?php endif; ?>
    </div>
    <div class="panel-form">
      <?php if ($err): ?><div class="flash err" style="margin:0 0 14px"><?= e($err) ?></div><?php endif; ?>
      <?php if ($ok): ?><div class="flash" style="margin:0 0 14px"><?= e($ok) ?></div><?php endif; ?>

      <?php if ($a === 'login'): ?>
      <form method="post" class="aform"><?= csrf_field() . $rt ?>
        <label>Username or email<span class="req">*</span><input name="log" required autofocus autocomplete="username" value="<?= e($_POST['log'] ?? '') ?>"></label>
        <label>Password<span class="req">*</span><input type="password" name="pwd" required autocomplete="current-password"></label>
        <div class="aform-row"><label class="check"><input type="checkbox" name="rememberme" value="1" checked> Remember Me!</label><a href="<?= url('lost-password/') ?>">Forgot Password?</a></div>
        <button class="btn-green wide" type="submit">Login</button>
      </form>
      <p class="aform-foot">Don't have account, <a href="<?= url('signup/') ?>">Sign Up Here</a></p>

      <?php elseif ($a === 'signup'): ?>
      <form method="post" class="aform"><?= csrf_field() . $rt ?>
        <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off">
        <label>Username<span class="req">*</span><input name="user_name" required maxlength="30" autocomplete="username" value="<?= e($_POST['user_name'] ?? '') ?>"></label>
        <label>E-Mail<span class="req">*</span><input type="email" name="email" required autocomplete="email" value="<?= e($_POST['email'] ?? '') ?>"></label>
        <label>Password<span class="req">*</span><input type="password" name="pass1" required minlength="6" autocomplete="new-password"></label>
        <label>Confirm Password<span class="req">*</span><input type="password" name="pass2" required minlength="6" autocomplete="new-password"></label>
        <label class="check"><input type="checkbox" name="agree" value="1" required> By registering, you agree to the <a href="<?= url('terms/') ?>">Terms of Service</a> and <a href="<?= url('privacy-policy/') ?>">Privacy Policy</a>.<span class="req">*</span></label>
        <button class="btn-green wide" type="submit">Signup</button>
      </form>
      <p class="aform-foot">Have an account? <a href="<?= url('log-in/') ?>">Sign In Now</a></p>

      <?php elseif ($a === 'lost'): ?>
      <form method="post" class="aform"><?= csrf_field() ?>
        <label>E-Mail<span class="req">*</span><input type="email" name="user_mail" required autofocus></label>
        <button class="btn-green wide" type="submit">Reset</button>
      </form>
      <p class="aform-foot">Have an account? <a href="<?= url('log-in/') ?>">Sign In Now</a></p>

      <?php elseif ($a === 'reset'): ?>
      <form method="post" class="aform"><?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($_GET['token'] ?? $_POST['token'] ?? '') ?>">
        <label>New password<span class="req">*</span><input type="password" name="pass1" required minlength="6" autocomplete="new-password"></label>
        <label>Confirm new password<span class="req">*</span><input type="password" name="pass2" required minlength="6" autocomplete="new-password"></label>
        <button class="btn-green wide" type="submit">Save new password</button>
      </form>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
