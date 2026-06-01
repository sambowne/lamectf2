<?php
include 'config.php';

function h($s) { return htmlspecialchars($s, ENT_QUOTES); }

$seconds = max(5, intval($_GET['seconds'] ?? 10));
$refresh = isset($_GET['refresh']) ? "<meta http-equiv='refresh' content='{$seconds}'>" : '';

function read_csv_file($path) {
    if (!file_exists($path)) return [];
    $lines = explode(PHP_EOL, file_get_contents($path));
    $rows = [];
    foreach ($lines as $line) {
        if (strlen(trim($line)) > 2) $rows[] = str_getcsv($line);
    }
    return $rows;
}

// ── Project scores from logfile ───────────────────────────────────────────────
$project_totals = [];
$seen = [];

foreach (read_csv_file($logfile) as $row) {
    if (!isset($row[0], $row[1], $row[2])) continue;
    $name = trim($row[0]);
    $chal = trim($row[1]);
    $pts  = intval($row[2]);
    if ($name === '' || $name === 'TESTING') continue;
    $key = $name . '|' . $chal;
    if (!isset($seen[$key])) {
        $seen[$key] = true;
        $project_totals[$name] = ($project_totals[$name] ?? 0) + $pts;
    }
}

// ── Extra credit from xfile ───────────────────────────────────────────────────
// "Extra" rows are summed per student; other rows use highest score, no duplicates
$xfile_clean  = [];
$extra_totals = [];
foreach (read_csv_file($xfile) as $row) {
    if (!isset($row[0], $row[1], $row[2])) continue;
    $name = trim($row[0]);
    $chal = trim($row[1]);
    $pts  = intval($row[2]);
    if ($name === '' || $name === 'TESTING') continue;
    if ($chal === 'Extra') {
        $extra_totals[$name] = ($extra_totals[$name] ?? 0) + $pts;
    } else {
        $key = $name . '|' . $chal;
        if (!isset($xfile_clean[$key]) || $pts > $xfile_clean[$key])
            $xfile_clean[$key] = $pts;
    }
}
foreach ($xfile_clean as $key => $pts) {
    if (isset($seen[$key])) continue;
    $name = explode('|', $key)[0];
    $project_totals[$name] = ($project_totals[$name] ?? 0) + $pts;
    $seen[$key] = true;
}

// ── Quiz scores (best attempt per quiz per student) ───────────────────────────
$quiz_best = []; // [name][quiz] = best score
foreach (read_csv_file($results_csv) as $row) {
    if (count($row) < 3) continue;
    $name  = trim($row[0]);
    $quiz  = trim($row[1]);
    $score = intval($row[2]);
    if ($name === '') continue;
    if (!isset($quiz_best[$name][$quiz]) || $score > $quiz_best[$name][$quiz]) {
        $quiz_best[$name][$quiz] = $score;
    }
}

$quiz_totals = [];
foreach ($quiz_best as $name => $quizzes) {
    $quiz_totals[$name] = array_sum($quizzes);
}

// ── Discussion scores ─────────────────────────────────────────────────────────
$discussion_totals = [];
if (!empty($discussions_enabled) && file_exists($discussions_csv)) {
    $fh = fopen($discussions_csv, 'r');
    if ($fh !== false) {
        while (($row = fgetcsv($fh)) !== false) {
            if (empty(trim($row[0]))) continue;
            $name = trim($row[0]);
            $sum  = 0;
            for ($d = 1; $d <= 12; $d++) $sum += intval($row[$d] ?? 0);
            $discussion_totals[$name] = ($discussion_totals[$name] ?? 0) + $sum;
        }
        fclose($fh);
    }
}

// ── Merge all nicknames ───────────────────────────────────────────────────────
$all_names = array_unique(array_merge(array_keys($project_totals), array_keys($quiz_totals), array_keys($extra_totals), array_keys($discussion_totals)));
$rows = [];
foreach ($all_names as $name) {
    $proj  = $project_totals[$name]    ?? 0;
    $quiz  = $quiz_totals[$name]       ?? 0;
    $extra = $extra_totals[$name]      ?? 0;
    $disc  = $discussion_totals[$name] ?? 0;
    $rows[] = ['name' => $name, 'projects' => $proj, 'quizzes' => $quiz, 'extra' => $extra, 'discussions' => $disc, 'total' => $proj + $quiz + $extra + $disc];
}

usort($rows, function($a, $b) { return $b['total'] - $a['total']; });
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<?php echo $refresh; ?>
<title>Overall Scoreboard</title>
<style>
  body { font-family: Arial, sans-serif; max-width: 700px; margin: 30px auto; padding: 0 20px; }
  h1 { color: #333; }
  table { border-collapse: collapse; width: 100%; }
  th, td { border: 1px solid #ccc; padding: 8px 14px; text-align: left; }
  th { background: #f0f0f0; }
  tr:nth-child(even) { background: #fafafa; }
  td.num { text-align: right; }
  td.rank { text-align: center; color: #888; }
</style>
</head>
<body>
<?php echo "<h2 align='center'>" . htmlspecialchars(strip_tags($description ?? ''), ENT_QUOTES) . "</h2>"; ?>
<?php include 'nav.php'; ?>
<h1>Overall Scoreboard</h1>
<table>
<tr><th>#</th><th>Nickname</th><th>Projects</th><th>Quizzes</th><?php if (!empty($discussions_enabled)): ?><th>Discussions</th><?php endif; ?><th>Extra Credit</th><th>Total</th></tr>
<?php foreach ($rows as $i => $r): ?>
<tr>
  <td class="rank"><?php echo $i + 1; ?></td>
  <td><?php echo h($r['name']); ?></td>
  <td class="num"><?php echo $r['projects']; ?></td>
  <td class="num"><?php echo $r['quizzes']; ?></td>
  <?php if (!empty($discussions_enabled)): ?><td class="num"><?php echo $r['discussions'] ?: ''; ?></td><?php endif; ?>
  <td class="num"><?php echo $r['extra'] ?: ''; ?></td>
  <td class="num"><strong><?php echo $r['total']; ?></strong></td>
</tr>
<?php endforeach; ?>
</table>
<?php include __DIR__ . '/nav_bottom.php'; ?>
</body></html>
