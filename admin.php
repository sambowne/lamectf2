<?php
session_start();
require __DIR__ . '/csrf.php';
include 'config.php';

$error  = '';
// Admin auth is per-class: valid only when session class matches current class
$authed = !empty($_SESSION['admin_authed']) &&
          !empty($_SESSION['admin_authed_class']) &&
          $_SESSION['admin_authed_class'] === ($course_number ?? '');

// ── Logout ────────────────────────────────────────────────────────────────────
if (isset($_GET['logout'])) {
    $_SESSION['admin_authed']       = false;
    $_SESSION['admin_authed_class'] = '';
    header('Location: admin.php' . $nav_c);
    exit;
}

// ── Backup Now ───────────────────────────────────────────────────────────────
$backup_ok  = '';
$backup_err = '';
if ($authed && isset($_POST['backup_now'])) {
    csrf_verify();
    $bpw = $backup_zip_password; // use stored zip password (set in System Settings)
    if (empty($backup_email) || empty($backup_agentmail_key)) {
        $backup_err = 'Backup not fully configured. Check Score Backup settings in Setup.';
    } else {
        // Collect score files from ALL configured classes
        $_bsd = ($_SERVER['HOME'] ?? posix_getpwuid(posix_geteuid())['dir']) . '/.lamectf2';
        $bpaths = [];
        foreach (glob($_bsd . '/*_secret.php') ?: [] as $_bsf) {
            $_bc = file_get_contents($_bsf);
            foreach (['logfile','xfile','results_csv','access_codes_csv','namefile'] as $_bv) {
                if (preg_match('/\$' . $_bv . '\s*=\s*([\'"])(.*?)\1/', $_bc, $_bm) && file_exists($_bm[2]))
                    $bpaths[] = $_bm[2];
            }
            if (preg_match('/\$course_number\s*=\s*([\'"])([A-Za-z0-9_-]+)\1/', $_bc, $_bm)) {
                foreach (['discussions.csv','grades.csv'] as $_bf) {
                    $_bp = $_bsd . '/' . $_bm[2] . '_' . $_bf;
                    if (file_exists($_bp)) $bpaths[] = $_bp;
                }
            }
        }
        $bpaths = array_unique($bpaths);
        if (empty($bpaths)) {
            $backup_err = 'No score files found.';
        } else {
            $btmp = tempnam(sys_get_temp_dir(),'lame_bk_').'.zip';
            $enc = false; $bdata = false;
            // Method 1: command-line zip with PKZIP password (works on most hosts)
            if (!empty($bpw) && function_exists('exec')) {
                $cmd_files = implode(' ', array_map('escapeshellarg', $bpaths));
                @exec('zip -j -P '.escapeshellarg($bpw).' '.escapeshellarg($btmp).' '.$cmd_files, $_o, $_r);
                if ($_r === 0 && file_exists($btmp)) { $enc = true; $bdata = file_get_contents($btmp); }
            }
            // Method 2: ZipArchive with AES-256 (requires libzip encryption support)
            if ($bdata === false) {
                $bzip = new ZipArchive();
                if ($bzip->open($btmp, ZipArchive::CREATE|ZipArchive::OVERWRITE) === true) {
                    foreach ($bpaths as $bp) $bzip->addFile($bp, basename($bp));
                    if (!empty($bpw) && method_exists($bzip,'setEncryptionIndex') && defined('ZipArchive::EM_AES_256')) {
                        $bzip->setPassword($bpw);
                        for ($bi=0;$bi<$bzip->numFiles;$bi++) $bzip->setEncryptionIndex($bi,ZipArchive::EM_AES_256);
                        $enc = true;
                    }
                    $bzip->close();
                    $bdata = file_get_contents($btmp);
                }
            }
            if ($bdata !== false) {
                    $bfname  = 'backup_all_' . date('Ymd_Hi') . '.zip';
                    $bsubj   = 'Score Backup (All Classes)';
                    $btext   = 'Score backup ('.count($bpaths).' files). '.($enc?'Encrypted.':'NOT encrypted.');
                    // send via agentmail
                    $bres = ['code'=>0,'body'=>'curl unavailable'];
                    if (function_exists('curl_init')) {
                        $ch = curl_init('https://api.agentmail.to/v0/inboxes');
                        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,
                            CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$backup_agentmail_key]]);
                        $raw=curl_exec($ch); curl_close($ch);
                        $inbox_data=json_decode($raw,true);
                        $inbox=$inbox_data[0]['username']??$inbox_data['inboxes'][0]['username']
                            ??$inbox_data[0]['inbox_id']??$inbox_data['inboxes'][0]['inbox_id']??null;
                        if (!$inbox) {
                            $bres=['code'=>0,'body'=>'Cannot determine inbox: '.substr($raw,0,400)];
                        } else {
                            $ch=curl_init("https://api.agentmail.to/v0/inboxes/".rawurlencode($inbox)."/messages/send");
                            curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,
                                CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$backup_agentmail_key,'Content-Type: application/json'],
                                CURLOPT_POSTFIELDS=>json_encode(['to'=>[$backup_email],'subject'=>$bsubj,'text'=>$btext,
                                    'attachments'=>[['filename'=>$bfname,'content'=>base64_encode($bdata),'content_type'=>'application/zip']]])]);
                            $br=curl_exec($ch); $bc=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
                            $bres=['code'=>$bc,'body'=>$br?:''];
                        }
                    }
                    if ($bres['code']>=200&&$bres['code']<300)
                        $backup_ok='Backup sent to '.htmlspecialchars($backup_email,ENT_QUOTES).'.';
                    else
                        $backup_err='AgentMail error '.$bres['code'].': '.htmlspecialchars(substr($bres['body'],0,200),ENT_QUOTES);
            } else { $backup_err='Could not create zip archive.'; }
        }
    }
}

// ── Login ─────────────────────────────────────────────────────────────────────
if (!$authed && isset($_POST['submit_login'])) {
    csrf_verify();
    if (empty($password_hash) || !password_verify($_POST['password'] ?? '', $password_hash)) {
        $error = 'Wrong password.';
    } else {
        $captcha_ok = true;
        if (!empty($recaptcha_secret)) {
            $token  = $_POST['g-recaptcha-response'] ?? '';
            $verify = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret={$recaptcha_secret}&response={$token}");
            $resp   = json_decode($verify);
            $captcha_ok = !empty($resp->success);
        }
        if (!$captcha_ok) {
            $error = 'CAPTCHA rejected.';
        } else {
            $_SESSION['admin_authed']       = true;
            $_SESSION['admin_authed_class'] = $course_number ?? '';
            session_regenerate_id(true);
            $authed = true;
            header('Location: admin.php' . $nav_c);
            exit;
        }
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Console</title>
<?php if (!empty($recaptcha_sitekey)): ?><script src="https://www.google.com/recaptcha/enterprise.js" async defer></script><?php endif; ?>
<style>
  body  { font-family: Arial, sans-serif; max-width: 800px; margin: 30px auto; padding: 0 20px; }
  .box  { background: #f8f8f8; border: 1px solid #ccc; padding: 18px 20px; border-radius: 6px; margin: 18px 0; }
  .box h2 { margin: 0 0 12px; font-size: 1.1em; }
  .err  { color: #c00; background: #fdd; border: 1px solid #c00; padding: 8px 12px; border-radius: 4px; margin: 10px 0; }
  .ok   { color: #040; background: #dfd; border: 1px solid #4a4; padding: 8px 12px; border-radius: 4px; margin: 10px 0; }
  label { display: block; margin: 8px 0 3px; font-weight: bold; }
  input[type=password], input[type=text], textarea { width: 100%; padding: 7px; font-size: 1em; box-sizing: border-box; }
  input[type=submit] { margin-top: 10px; padding: 8px 22px; font-size: 1em; cursor: pointer; }
  input[type=checkbox] { width: auto; }
  nav.admin-nav { text-align: right; font-size: 0.9em; margin-bottom: 6px; }
  .links a { display: inline-block; margin: 4px 10px 4px 0; font-size: 1.05em; }
</style>
</head>
<body>
<?php echo "<h2 align='center'>" . htmlspecialchars(strip_tags($description), ENT_QUOTES) . "</h2>"; ?>

<?php if (!$authed): ?>

<?php if ($error): ?><div class="err"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="box" style="max-width:400px;margin:40px auto">
  <h2 align="center">Admin Login</h2>
  <form method="post" action="admin.php<?php echo htmlspecialchars($nav_c, ENT_QUOTES); ?>">
    <?php echo csrf_field(); ?>
    <?php if (!empty($recaptcha_sitekey)): ?>
    <div align="center">
      <div class="g-recaptcha" data-sitekey="<?php echo htmlspecialchars($recaptcha_sitekey); ?>" data-action="LOGIN"></div>
    </div>
    <?php endif; ?>
    <label>Password:</label>
    <input type="password" name="password" autofocus>
    <input type="submit" name="submit_login" value="Log In">
  </form>
</div>

<?php else: ?>

<nav class="admin-nav"><a href="admin.php<?php echo htmlspecialchars($nav_c, ENT_QUOTES); ?>&logout=1">Log out</a></nav>
<?php include 'nav.php'; ?>

<div class="box links">
  <h2>Admin Links</h2>
  <a href="grid.php<?php echo htmlspecialchars($nav_c, ENT_QUOTES); ?>"><strong>Score Grid</strong></a>
  <a href="scoreboard_with_names.php<?php echo htmlspecialchars($nav_c, ENT_QUOTES); ?>">Scores with Names</a>
  <a href="extra.php<?php echo htmlspecialchars($nav_c, ENT_QUOTES); ?>">Record Extra Credit</a>
  <?php if (!empty($discussions_enabled)): ?>
  <a href="discussions.php<?php echo htmlspecialchars($nav_c, ENT_QUOTES); ?>">Discussion Scores</a>
  <?php endif; ?>
  <a href="nick_access.php<?php echo htmlspecialchars($nav_c, ENT_QUOTES); ?>">Nicknames &amp; Access Codes</a>
  <a href="quiz_upload.php<?php echo htmlspecialchars($nav_c, ENT_QUOTES); ?>">Quiz Files</a>
  <a href="init.php?new=1">Add Another Class</a>
</div>

<?php if (!empty($backup_enabled) && !empty($backup_email)): ?>
<div class="box">
  <h2>Backup Now</h2>
  <?php if ($backup_ok): ?><div class="ok">&#10003; <?php echo $backup_ok; ?></div><?php endif; ?>
  <?php if ($backup_err): ?><div class="err">&#10007; <?php echo $backup_err; ?></div><?php endif; ?>
  <form method="post" style="max-width:380px">
    <?php echo csrf_field(); ?>
    <input type="submit" name="backup_now" value="Send Backup Now">
  </form>
  <p style="margin-top:10px"><a href="backup_log.php">View Backup Log &rarr;</a></p>
</div>
<?php endif; ?>


<?php endif; ?>

<?php include __DIR__ . '/nav_bottom.php'; ?>
</body>
</html>
