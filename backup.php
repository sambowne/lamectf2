<?php
session_start();
require __DIR__ . '/csrf.php';
include 'config.php';

if (empty($_SESSION['admin_authed']) ||
    empty($_SESSION['admin_authed_class']) ||
    $_SESSION['admin_authed_class'] !== ($course_number ?? '')) {
    header('Location: admin.php' . $nav_c);
    exit;
}

function h($s) { return htmlspecialchars($s, ENT_QUOTES); }

function resolve_path($p) {
    return (isset($p[0]) && $p[0] === '/') ? $p : __DIR__ . '/' . $p;
}

function agentmail_send($api_key, $to, $subject, $text, $filename, $zip_data) {
    if (!function_exists('curl_init')) return ['code' => 0, 'body' => 'curl not available'];

    // Step 1: find inbox username from API key
    $ch = curl_init('https://api.agentmail.to/v0/inboxes');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $api_key],
    ]);
    $raw  = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($raw, true);
    $inbox = $data[0]['username']
          ?? $data['inboxes'][0]['username']
          ?? null;
    if (!$inbox) {
        return ['code' => 0, 'body' => 'Cannot determine AgentMail inbox. Response: ' . substr($raw, 0, 300)];
    }

    // Step 2: send message
    $ch = curl_init("https://api.agentmail.to/v0/inboxes/{$inbox}/messages");
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $api_key,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'to'          => [$to],
            'subject'     => $subject,
            'text'        => $text,
            'attachments' => [[
                'filename' => $filename,
                'content'  => base64_encode($zip_data),
                'encoding' => 'base64',
                'type'     => 'application/zip',
            ]],
        ]),
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => $resp ?: ''];
}

function create_zip_backup($zip_password) {
    global $logfile, $xfile, $results_csv, $discussions_csv, $grades_csv, $course_number;

    $paths = array_filter(array_map('resolve_path', [$logfile, $xfile, $results_csv, $discussions_csv, $grades_csv]), 'file_exists');
    if (empty($paths)) return ['error' => 'No score files found to back up.'];

    $tmpfile = tempnam(sys_get_temp_dir(), 'lame_bk_') . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($tmpfile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return ['error' => 'Could not create zip archive.'];
    }
    foreach ($paths as $p) $zip->addFile($p, basename($p));
    $encrypted = false;
    if ($zip_password !== '' && method_exists($zip, 'setEncryptionIndex') && defined('ZipArchive::EM_AES_256')) {
        $zip->setPassword($zip_password);
        for ($i = 0; $i < $zip->numFiles; $i++) $zip->setEncryptionIndex($i, ZipArchive::EM_AES_256);
        $encrypted = true;
    }
    $zip->close();
    $data = file_get_contents($tmpfile);
    @unlink($tmpfile);
    if ($data === false) return ['error' => 'Could not read generated zip.'];

    $prefix = !empty($course_number) ? $course_number . '_' : '';
    return [
        'data'      => $data,
        'filename'  => $prefix . 'backup_' . date('Ymd_Hi') . '.zip',
        'count'     => count($paths),
        'encrypted' => $encrypted,
    ];
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_backup'])) {
    csrf_verify();
    $pw = $_POST['admin_password'] ?? '';

    if (!password_verify($pw, $password_hash)) {
        $error = 'Wrong admin password.';
    } elseif (empty($backup_email)) {
        $error = 'No destination email configured. Enable and configure Score Backup in Setup.';
    } elseif (empty($backup_agentmail_key)) {
        $error = 'No AgentMail API key configured.';
    } else {
        $zip = create_zip_backup($pw);
        if (isset($zip['error'])) {
            $error = $zip['error'];
        } else {
            $enc_note = $zip['encrypted']
                ? 'Zip is password-protected (AES-256) with the admin password.'
                : 'Note: zip is NOT encrypted — AES encryption unavailable on this server.';
            $result = agentmail_send(
                $backup_agentmail_key,
                $backup_email,
                'Score Backup: ' . $description . ' — ' . date('Y-m-d H:i'),
                'Score backup attached (' . $zip['count'] . ' files). ' . $enc_note,
                $zip['filename'],
                $zip['data']
            );
            if ($result['code'] >= 200 && $result['code'] < 300) {
                $success = 'Backup sent to ' . h($backup_email) . '. ' . $enc_note;
            } else {
                $error = 'AgentMail API returned ' . $result['code'] . ': ' . h(substr($result['body'], 0, 300));
            }
        }
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Send Score Backup</title>
<style>
  body { font-family: Arial, sans-serif; max-width: 600px; margin: 40px auto; padding: 0 20px; }
  h1   { color: #333; }
  label { display: block; margin: 12px 0 4px; font-weight: bold; }
  input[type=password], input[type=submit] { padding: 8px; font-size: 1em; }
  input[type=password] { width: 100%; box-sizing: border-box; }
  .ok  { background: #dfd; border: 1px solid #4a4; padding: 10px 14px; border-radius: 4px; color: #040; margin: 12px 0; }
  .err { background: #fdd; border: 1px solid #c00; padding: 10px 14px; border-radius: 4px; color: #900; margin: 12px 0; }
</style>
</head>
<body>
<?php include 'nav.php'; ?>
<h1>Send Score Backup</h1>
<?php if ($success): ?>
<div class="ok">&#10003; <?php echo $success; ?></div>
<p><a href="admin.php">&larr; Back to Admin</a></p>
<?php else: ?>
<?php if ($error): ?><div class="err">&#10007; <?php echo $error; ?></div><?php endif; ?>
<p>This will zip all five score files, encrypt the archive, and email it to <strong><?php echo h($backup_email ?: '(not configured)'); ?></strong>.</p>
<form method="post">
  <?php echo csrf_field(); ?>
  <label>Admin Password <small>(used to encrypt the zip)</small>:</label>
  <input type="password" name="admin_password" autofocus>
  <br><br>
  <input type="submit" name="send_backup" value="Send Backup Now">
</form>
<p><a href="admin.php">&larr; Cancel</a></p>
<?php endif; ?>
<?php include __DIR__ . '/nav_bottom.php'; ?>
</body>
</html>
