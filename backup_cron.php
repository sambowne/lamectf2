<?php
// CLI-only: called by cron. Never served via web.
if (php_sapi_name() !== 'cli') { http_response_code(403); exit; }

$_secret_dir = (getenv('HOME') ?: posix_getpwuid(posix_geteuid())['dir']) . '/.lamectf2';

// ── Load global backup config ────────────────────────────────────────────────
$backup_email        = '';
$backup_agentmail_key = '';
$backup_zip_password = '';
$backup_enabled      = false;

$_backup_cfg = $_secret_dir . '/backup.php';
if (file_exists($_backup_cfg)) include $_backup_cfg;

if (empty($backup_enabled) || empty($backup_email) || empty($backup_agentmail_key)) {
    echo date('Y-m-d H:i:s') . " backup_cron: backup not enabled or not configured, skipping.\n";
    exit;
}

// ── Collect score files from ALL classes ─────────────────────────────────────
$paths = [];
foreach (glob($_secret_dir . '/*_secret.php') ?: [] as $_sf) {
    $_bc = file_get_contents($_sf);
    foreach (['logfile','xfile','results_csv','access_codes_csv','namefile'] as $_bv) {
        if (preg_match('/\$' . $_bv . '\s*=\s*([\'"])(.*?)\1/', $_bc, $_bm) && file_exists($_bm[2]))
            $paths[] = $_bm[2];
    }
    if (preg_match('/\$course_number\s*=\s*([\'"])([A-Za-z0-9_-]+)\1/', $_bc, $_bm)) {
        foreach (['discussions.csv','grades.csv'] as $_bf) {
            $_bp = $_secret_dir . '/' . $_bm[2] . '_' . $_bf;
            if (file_exists($_bp)) $paths[] = $_bp;
        }
    }
}
$paths = array_unique($paths);

if (empty($paths)) {
    echo date('Y-m-d H:i:s') . " backup_cron: no score files found.\n";
    exit;
}

// ── Create zip ───────────────────────────────────────────────────────────────
$tmpfile = tempnam(sys_get_temp_dir(), 'lame_bk_') . '.zip';
$encrypted = false;
$zpw = $backup_zip_password;
$data = false;

// Method 1: command-line zip with PKZIP password (works on most hosts)
if ($zpw !== '' && function_exists('exec')) {
    $cmd_files = implode(' ', array_map('escapeshellarg', $paths));
    @exec('zip -j -P ' . escapeshellarg($zpw) . ' ' . escapeshellarg($tmpfile) . ' ' . $cmd_files, $_o, $_r);
    if ($_r === 0 && file_exists($tmpfile)) { $encrypted = true; $data = file_get_contents($tmpfile); }
}

// Method 2: ZipArchive with AES-256 (requires libzip encryption support)
if ($data === false) {
    $zip = new ZipArchive();
    if ($zip->open($tmpfile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        echo date('Y-m-d H:i:s') . " backup_cron: could not create zip.\n";
        exit;
    }
    foreach ($paths as $p) $zip->addFile($p, basename($p));
    if ($zpw !== '' && method_exists($zip, 'setEncryptionIndex') && defined('ZipArchive::EM_AES_256')) {
        $zip->setPassword($zpw);
        for ($i = 0; $i < $zip->numFiles; $i++) $zip->setEncryptionIndex($i, ZipArchive::EM_AES_256);
        $encrypted = true;
    }
    $zip->close();
    $data = file_get_contents($tmpfile);
}
@unlink($tmpfile);
if ($data === false) { echo date('Y-m-d H:i:s') . " backup_cron: could not read zip.\n"; exit; }

// ── Send via AgentMail ────────────────────────────────────────────────────────
$fname   = 'backup_all_' . date('Ymd_Hi') . '.zip';
$enc_note = $encrypted ? 'Encrypted.' : 'NOT encrypted.';
$subject = 'Nightly Score Backup';
$body    = 'Automated nightly score backup (' . count($paths) . ' files from all classes). ' . $enc_note;

echo date('Y-m-d H:i:s') . " backup_cron: sending to $backup_email ... ";

if (!function_exists('curl_init')) { echo "curl not available\n"; exit; }
$ch = curl_init('https://api.agentmail.to/v0/inboxes');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $backup_agentmail_key]]);
$raw  = curl_exec($ch); curl_close($ch);
$idata = json_decode($raw, true);
$inbox = $idata[0]['username'] ?? $idata['inboxes'][0]['username']
      ?? $idata[0]['inbox_id'] ?? $idata['inboxes'][0]['inbox_id'] ?? null;
if (!$inbox) { echo "Cannot determine inbox. Response: " . substr($raw, 0, 400) . "\n"; exit; }

$ch = curl_init("https://api.agentmail.to/v0/inboxes/" . rawurlencode($inbox) . "/messages/send");
curl_setopt_array($ch, [
    CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $backup_agentmail_key, 'Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode([
        'to' => [$backup_email], 'subject' => $subject, 'text' => $body,
        'attachments' => [['filename' => $fname, 'content' => base64_encode($data),
            'content_type' => 'application/zip']],
    ]),
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
if ($code >= 200 && $code < 300) { echo "Sent OK ($code)\n"; }
else { echo "API error $code: " . substr($resp, 0, 200) . "\n"; }
