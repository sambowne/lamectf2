<?php
session_start();
require __DIR__ . '/csrf.php';
include 'config.php';

$secret_dir = ($_SERVER['HOME'] ?? posix_getpwuid(posix_geteuid())['dir']) . '/.lamectf2';

if (empty($_SESSION['admin_authed']) ||
    empty($_SESSION['admin_authed_class']) ||
    $_SESSION['admin_authed_class'] !== ($course_number ?? '')) {
    header('Location: admin.php' . $nav_c);
    exit;
}

function h($s) { return htmlspecialchars($s, ENT_QUOTES); }

$log_path = $secret_dir . '/backup.log';
$log_lines = [];
if (file_exists($log_path)) {
    $all = file($log_path, FILE_IGNORE_NEW_LINES);
    if ($all !== false) {
        $log_lines = array_slice($all, -100);
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Backup Log</title>
<style>
  body { font-family: Arial, sans-serif; max-width: 960px; margin: 30px auto; padding: 0 20px; }
  h2 { color: #333; }
  pre { background:#1e1e1e; color:#d4d4d4; padding:16px; border-radius:6px; overflow-x:auto; font-size:0.85em; white-space:pre-wrap; word-break:break-all; }
  .none { color:#888; font-style:italic; }
</style>
</head>
<body>
<?php echo "<h2 align='center'>" . h(strip_tags($description ?? '')) . "</h2>"; ?>
<?php include 'nav.php'; ?>
<h2>Backup Log</h2>
<p>Last 100 lines of <code><?php echo h($log_path); ?></code>:</p>
<?php if (empty($log_lines)): ?>
  <p class="none">No log file found yet. Logs will appear here after the first automated backup runs.</p>
<?php else: ?>
  <pre><?php echo h(implode("\n", $log_lines)); ?></pre>
<?php endif; ?>
<p><a href="admin.php">&larr; Back to Admin</a></p>
<?php include __DIR__ . '/nav_bottom.php'; ?>
</body>
</html>
