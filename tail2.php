<?php
include 'config.php';

$count   = max(1, intval($_GET['count']   ?? 20));
$seconds = max(5, intval($_GET['seconds'] ?? 10));
$refresh = isset($_GET['refresh']) ? "<meta http-equiv='refresh' content='{$seconds}'>" : '';

if (!isset($logfile)) { exit('Error: logfile not set in config.php'); }

$logfile_dated = $logfile . 'w-date';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Recent Scores</title>
<?php echo $refresh; ?>
<style>
  body  { font-family: Arial, sans-serif; max-width: 700px; margin: 30px auto; padding: 0 20px; }
  table { border-collapse: collapse; width: 100%; margin-top: 16px; }
  th, td { border: 1px solid #ccc; padding: 8px 14px; text-align: left; }
  th { background: #f0f0f0; }
  tr:nth-child(even) { background: #fafafa; }
  .meta { color: #555; font-size: 0.88em; margin: 8px 0 0; }
</style>
</head>
<body>
<?php
echo "<h2 align='center'>" . htmlspecialchars(strip_tags($description), ENT_QUOTES) . "</h2>";
include 'nav.php';
echo "<h2>Recent Scores</h2>";

if (!file_exists($logfile_dated)) {
    echo "<p>No scores recorded yet.</p>";
    include __DIR__ . '/nav_bottom.php';
    echo "</body></html>";
    exit;
}

$csv      = array_map('str_getcsv', file($logfile_dated, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
$numlines = count($csv);
$start    = max(0, $numlines - $count);

echo "<p class='meta'>Showing last $count of $numlines entries";
if (isset($_GET['refresh'])) echo " &nbsp;&middot;&nbsp; Refreshing every {$seconds}s";
echo " &nbsp;&middot;&nbsp; <a href='tail2.php?count={$count}&seconds={$seconds}&refresh=1'>Auto-refresh on</a>"
   . " &nbsp;|&nbsp; <a href='tail2.php?count={$count}&seconds={$seconds}'>off</a></p>";

echo "<table><tr><th>Nickname</th><th>Challenge</th><th>Points</th><th>Time</th></tr>";
for ($i = $numlines - 1; $i >= $start; $i--) {
    $row = $csv[$i];
    echo "<tr>"
       . "<td>" . htmlspecialchars($row[0] ?? '') . "</td>"
       . "<td>" . htmlspecialchars($row[1] ?? '') . "</td>"
       . "<td>" . htmlspecialchars($row[2] ?? '') . "</td>"
       . "<td>" . htmlspecialchars($row[3] ?? '') . "</td>"
       . "</tr>\n";
}
echo "</table>";
?>
<?php include __DIR__ . '/nav_bottom.php'; ?>
</body>
</html>
