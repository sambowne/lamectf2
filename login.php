<?php
session_start();
require __DIR__ . '/csrf.php';
include 'config.php';

function nick_exists($nickname, $namefile) {
    if ($nickname === '' || !file_exists($namefile)) return false;
    $fh = fopen($namefile, 'r');
    if ($fh === false) return false;
    while (($row = fgetcsv($fh)) !== false) {
        if (trim($row[0]) === $nickname) { fclose($fh); return true; }
    }
    fclose($fh);
    return false;
}

function validate_login($nickname, $code, $csv_path) {
    if ($nickname === '' || $code === '') return false;
    if (!file_exists($csv_path)) return false;
    $fh = fopen($csv_path, 'r');
    if ($fh === false) return false;
    fgetcsv($fh); // skip header
    while (($row = fgetcsv($fh)) !== false) {
        if (trim($row[0]) === $nickname) {
            fclose($fh);
            return strtolower(trim($row[1] ?? '')) === strtolower($code);
        }
    }
    fclose($fh);
    return false;
}

$_k_nick = 'student_nick_' . ($course_number ?? 'default');
$_k_code = 'student_code_' . ($course_number ?? 'default');

// Logout
if (isset($_GET['logout'])) {
    unset($_SESSION[$_k_nick], $_SESSION[$_k_code]);
    header('Location: login.php' . $nav_c);
    exit;
}

// Already logged in
if (!empty($_SESSION[$_k_nick])) {
    header('Location: index.php' . $nav_c);
    exit;
}

$error      = '';
$registered = !empty($_GET['registered']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $nick = trim($_POST['name'] ?? '');
    $code = trim($_POST['code'] ?? '');
    // reCAPTCHA verification (only when keys are configured)
    $captcha_ok = true;
    if (!empty($recaptcha_secret)) {
        $token      = $_POST['g-recaptcha-response'] ?? '';
        $verify     = @file_get_contents('https://www.google.com/recaptcha/api/siteverify?secret='
                        . urlencode($recaptcha_secret) . '&response=' . urlencode($token));
        $resp       = $verify ? json_decode($verify) : null;
        $captcha_ok = !empty($resp->success);
    }
    if (!$captcha_ok) {
        $error = 'CAPTCHA rejected. Please try again.';
    } elseif (($open_ctf ?? false) && nick_exists($nick, $namefile)) {
        $_SESSION[$_k_nick] = $nick;
        session_regenerate_id(true);
        header('Location: index.php' . $nav_c);
        exit;
    } elseif (!($open_ctf ?? false) && validate_login($nick, $code, $access_codes_csv)) {
        $_SESSION[$_k_nick] = $nick;
        $_SESSION[$_k_code] = $code;
        session_regenerate_id(true);
        header('Location: index.php' . $nav_c);
        exit;
    } elseif ($captcha_ok) {
        $error = ($open_ctf ?? false) ? 'Nickname not found.' : 'Invalid nickname or access code.';
    }
}

$has_students = file_exists($namefile) && filesize($namefile) > 0;
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Student Login</title>
<?php if (!empty($recaptcha_sitekey)): ?><script src="https://www.google.com/recaptcha/enterprise.js" async defer></script><?php endif; ?>
<style>
  body  { font-family: Arial, sans-serif; max-width: 500px; margin: 40px auto; padding: 0 20px; }
  .box  { background: #f8f8f8; border: 1px solid #ccc; padding: 20px; border-radius: 6px; }
  .err  { color: #c00; background: #fdd; border: 1px solid #c00; padding: 8px 12px; border-radius: 4px; margin: 10px 0; }
  .ok   { color: #040; background: #dfd; border: 1px solid #4a4; padding: 8px 12px; border-radius: 4px; margin: 10px 0; }
  label { display: block; margin: 10px 0 3px; font-weight: bold; }
  select, input[type=text] { width: 100%; padding: 7px; font-size: 1em; box-sizing: border-box; }
  input[type=submit] { margin-top: 14px; padding: 9px 24px; font-size: 1em; cursor: pointer; }
</style>
</head>
<body>
<?php echo "<h2 align='center'>" . htmlspecialchars(strip_tags($description ?? ''), ENT_QUOTES) . "</h2>"; ?>
<div class="box">
  <h2 align="center">Student Login</h2>
  <?php if ($registered): ?><div class="ok">Registration successful! Enter your nickname and access code to log in.</div><?php endif; ?>
  <?php if ($error): ?><div class="err"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
  <?php if (!$has_students): ?>
  <p>No students registered yet. <a href="register_form.php<?php echo htmlspecialchars($nav_c, ENT_QUOTES); ?>"><strong>Register now</strong></a>.</p>
  <?php else: ?>
  <form method="post" action="login.php<?php echo htmlspecialchars($nav_c, ENT_QUOTES); ?>">
    <?php echo csrf_field(); ?>
    <label for="name">Nickname:</label>
    <select name="name" id="name" required>
      <option value="">-- select --</option>
      <?php
      $fh = fopen($namefile, 'r');
      while ($fh !== false && ($row = fgetcsv($fh)) !== false) {
          $n = htmlspecialchars(trim($row[0]), ENT_QUOTES);
          echo "      <option value=\"$n\">$n</option>\n";
      }
      if ($fh) fclose($fh);
      ?>
    </select>
    <?php if (!($open_ctf ?? false)): ?>
    <label for="code">Access Code:</label>
    <input type="text" name="code" id="code" placeholder="e.g. open_apple" autocomplete="off" required>
    <?php endif; ?>
    <?php if (!empty($recaptcha_sitekey)): ?>
    <div style="margin-top:12px">
      <div class="g-recaptcha" data-sitekey="<?php echo htmlspecialchars($recaptcha_sitekey); ?>" data-action="LOGIN"></div>
    </div>
    <?php endif; ?>
    <input type="submit" value="Log In">
  </form>
  <?php endif; ?>
  <p style="margin-top:16px; text-align:center">New student? <a href="register_form.php<?php echo htmlspecialchars($nav_c, ENT_QUOTES); ?>">Register here</a></p>
</div>
<nav style="margin-top:30px; padding:10px; background:#f0f0f0; border-radius:6px; text-align:center; font-size:0.9em;">
  <a href="scoreboard_overall.php<?php echo htmlspecialchars($nav_c, ENT_QUOTES); ?>">Class Scoreboard</a> &nbsp;|&nbsp;
  <a href="admin.php<?php echo htmlspecialchars($nav_c, ENT_QUOTES); ?>">Admin</a>
</nav>
</body>
</html>
