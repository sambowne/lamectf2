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

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES); }

// Load access codes CSV
$rows = [];
if (file_exists($access_codes_csv)) {
    $fh = fopen($access_codes_csv, 'r');
    $header = fgetcsv($fh); // skip header row
    while (($row = fgetcsv($fh)) !== false) {
        if (count($row) >= 2 && trim($row[0]) !== '') {
            $rows[] = ['nickname' => trim($row[0]), 'code' => trim($row[1])];
        }
    }
    fclose($fh);
}
usort($rows, function($a, $b) { return strcmp($a['nickname'], $b['nickname']); });
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Nicknames &amp; Access Codes</title>
<style>
  body  { font-family: Arial, sans-serif; max-width: 600px; margin: 30px auto; padding: 0 20px; }
  table { border-collapse: collapse; width: 100%; margin: 16px 0; }
  th, td { border: 1px solid #ccc; padding: 8px 14px; text-align: left; }
  th { background: #f0f0f0; }
  tr:nth-child(even) { background: #fafafa; }
</style>
</head>
<body>
<?php echo "<h2 align='center'>" . htmlspecialchars(strip_tags($description ?? ''), ENT_QUOTES) . "</h2>"; ?>
<?php include 'nav.php'; ?>

<p><a href="admin.php<?php echo h($nav_c); ?>">&larr; Admin</a></p>
<h2>Nicknames &amp; Access Codes</h2>

<?php if (empty($rows)): ?>
<p>No access codes found in <code><?php echo h($access_codes_csv); ?></code>.</p>
<?php else: ?>
<p><?php echo count($rows); ?> student(s)</p>
<table>
  <tr><th>#</th><th>Nickname</th><th>Access Code</th></tr>
  <?php foreach ($rows as $i => $r): ?>
  <tr>
    <td><?php echo $i + 1; ?></td>
    <td><?php echo h($r['nickname']); ?></td>
    <td><?php echo h($r['code']); ?></td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<?php include __DIR__ . '/nav_bottom.php'; ?>
</body>
</html>
