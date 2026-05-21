<?php
session_start();
include 'config.php';
// ─────────────────────────────────────────────────────────────────────────────

function h($s) { return htmlspecialchars($s, ENT_QUOTES); }

function validate_access_code($nickname, $code, $csv_path) {
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

function nickname_dropdown_sb($namefile, $selected = '') {
    echo '<select name="name" id="name">';
    echo '<option value="">-- choose --</option>';
    if (isset($namefile) && file_exists($namefile)) {
        $fh = fopen($namefile, 'r');
        while ($fh !== false && ($row = fgetcsv($fh)) !== false) {
            $n   = htmlspecialchars($row[0], ENT_QUOTES);
            $sel = ($row[0] === $selected) ? ' selected' : '';
            echo "<option value=\"$n\"$sel>$n</option>";
        }
        fclose($fh);
    }
    echo '</select>';
}

function read_csv_file($path) {
    if (!file_exists($path)) return [];
    $lines = explode(PHP_EOL, file_get_contents($path));
    $rows = [];
    foreach ($lines as $line) {
        if (strlen(trim($line)) > 2) $rows[] = str_getcsv($line);
    }
    return $rows;
}

$_k_nick = 'student_nick_' . ($course_number ?? 'default');
$_k_code = 'student_code_' . ($course_number ?? 'default');

if (empty($_SESSION[$_k_nick])) {
    header('Location: login.php' . $nav_c);
    exit;
}

$nickname = $_SESSION[$_k_nick];
$code     = $_SESSION[$_k_code] ?? '';
$nav_qs   = '';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Score</title>
<style>
  body { font-family: Arial, sans-serif; max-width: 860px; margin: 30px auto; padding: 0 20px; }
  h1, h2 { color: #333; }
  table { border-collapse: collapse; width: 100%; margin: 12px 0 24px; }
  th, td { border: 1px solid #ccc; padding: 8px 12px; text-align: left; }
  th { background: #f0f0f0; }
  .total-row td { font-weight: bold; background: #e8f4e8; }
  .grand-total td { font-weight: bold; font-size: 1.1em; background: #d0eaff; }
  select, input[type=submit] { padding: 6px 14px; font-size: 1em; }
  input[type=text] { padding: 6px; font-size: 1em; }
</style>
</head>
<body>
<?php echo "<h2 align='center'>" . htmlspecialchars(strip_tags($description ?? ''), ENT_QUOTES) . "</h2>"; ?>
<?php include 'nav.php'; ?>
<h1>My Score</h1>
<?php

echo '<p>Nickname: <strong>' . h($nickname) . '</strong></p>';

// ── Project score from logfile + xfile ───────────────────────────────────────
$project_score = 0;
$project_rows  = [];
$seen_chals    = [];

$log_rows = read_csv_file($logfile);
foreach ($log_rows as $row) {
    if (!isset($row[0], $row[1], $row[2])) continue;
    $name = trim($row[0]); $chal = trim($row[1]); $pts = intval($row[2]);
    if ($name !== $nickname || $name === 'TESTING') continue;
    $key = $name . '|' . $chal;
    if (!isset($seen_chals[$key])) {
        $seen_chals[$key] = $pts;
        $project_rows[] = ['chal' => $chal, 'pts' => $pts, 'source' => 'project'];
        $project_score += $pts;
    }
}

$xfile_clean = [];
foreach (read_csv_file($xfile) as $row) {
    if (!isset($row[0], $row[1], $row[2])) continue;
    $name = trim($row[0]); $chal = trim($row[1]); $pts = intval($row[2]);
    $key  = $name . '|' . $chal;
    if ($chal === 'Extra') {
        // Sum all extra credit entries for the same student
        if (!isset($xfile_clean[$key]))
            $xfile_clean[$key] = ['name' => $name, 'chal' => $chal, 'pts' => 0];
        $xfile_clean[$key]['pts'] += $pts;
    } else {
        if (!isset($xfile_clean[$key]) || $pts > $xfile_clean[$key]['pts'])
            $xfile_clean[$key] = ['name' => $name, 'chal' => $chal, 'pts' => $pts];
    }
}
foreach ($xfile_clean as $key => $xrow) {
    if ($xrow['name'] !== $nickname || isset($seen_chals[$key])) continue;
    $project_rows[] = ['chal' => $xrow['chal'], 'pts' => $xrow['pts'], 'source' => 'extra credit'];
    $project_score += $xrow['pts'];
    $seen_chals[$key] = $xrow['pts'];
}

echo '<h2>Project Score</h2>';
if (empty($project_rows)) {
    echo '<p><em>No project scores found for this nickname.</em></p>';
} else {
    echo '<table><tr><th>Challenge</th><th>Points</th><th>Type</th></tr>';
    foreach ($project_rows as $pr)
        echo '<tr><td>' . h($pr['chal']) . '</td><td>' . $pr['pts'] . '</td><td>' . h($pr['source']) . '</td></tr>';
    echo '<tr class="total-row"><td>Total Project Points</td><td>' . $project_score . '</td><td></td></tr>';
    echo '</table>';
}

// ── Quiz scores ───────────────────────────────────────────────────────────────
$quiz_attempts = [];
$quiz_score    = 0;

if (file_exists($results_csv)) {
    $fh = fopen($results_csv, 'r');
    while ($fh !== false && ($row = fgetcsv($fh)) !== false) {
        if (count($row) < 4) continue;
        [$nick, $quiz, $score, $ts] = array_map('trim', $row);
        if ($nick !== $nickname) continue;
        $quiz_attempts[$quiz][] = ['score' => intval($score), 'ts' => $ts];
    }
    fclose($fh);
}

echo '<h2>Quiz Scores</h2>';
if (empty($quiz_attempts)) {
    echo '<p><em>No quiz attempts found for this nickname.</em></p>';
} else {
    echo '<table><tr><th>Quiz</th><th>Attempt</th><th>Score</th><th>Date/Time</th></tr>';
    foreach ($quiz_attempts as $qtitle => $attempts) {
        $best = 0;
        foreach ($attempts as $ai => $att) {
            if ($att['score'] > $best) $best = $att['score'];
            echo '<tr><td>' . h($qtitle) . '</td><td>' . ($ai+1) . '</td><td>' . $att['score'] . '</td><td>' . h($att['ts']) . '</td></tr>';
        }
        echo '<tr class="total-row"><td>' . h($qtitle) . ' — best score</td><td></td><td>' . $best . '</td><td></td></tr>';
        $quiz_score += $best;
    }
    echo '<tr class="total-row"><td>Total Quiz Points (best per quiz)</td><td></td><td>' . $quiz_score . '</td><td></td></tr>';
    echo '</table>';
}

// ── Discussion scores ─────────────────────────────────────────────────────────
$discussion_score = 0;
$discussion_rows  = [];
if (!empty($discussions_enabled) && file_exists($discussions_csv)) {
    $fh = fopen($discussions_csv, 'r');
    if ($fh !== false) {
        while (($row = fgetcsv($fh)) !== false) {
            if (empty(trim($row[0]))) continue;
            if (trim($row[0]) !== $nickname) continue;
            for ($d = 1; $d <= 12; $d++) {
                $v = intval($row[$d] ?? 0);
                if ($v > 0) {
                    $discussion_rows[] = ['disc' => "Discussion $d", 'pts' => $v];
                    $discussion_score += $v;
                }
            }
        }
        fclose($fh);
    }
}

if (!empty($discussions_enabled)) {
    echo '<h2>Discussion Scores</h2>';
    if (empty($discussion_rows)) {
        echo '<p><em>No discussion scores recorded for this nickname.</em></p>';
    } else {
        echo '<table><tr><th>Discussion</th><th>Points</th></tr>';
        foreach ($discussion_rows as $dr)
            echo '<tr><td>' . h($dr['disc']) . '</td><td>' . $dr['pts'] . '</td></tr>';
        echo '<tr class="total-row"><td>Total Discussion Points</td><td>' . $discussion_score . '</td></tr>';
        echo '</table>';
    }
}

// ── Grand total ───────────────────────────────────────────────────────────────
$grand_total = $project_score + $quiz_score + $discussion_score;
echo '<h2>Overall Total</h2>';
echo '<table>';
echo '<tr><th>Category</th><th>Points</th></tr>';
echo '<tr><td>Projects</td><td>' . $project_score . '</td></tr>';
echo '<tr><td>Quizzes</td><td>' . $quiz_score . '</td></tr>';
if (!empty($discussions_enabled))
    echo '<tr><td>Discussions</td><td>' . $discussion_score . '</td></tr>';
echo '<tr class="grand-total"><td>Grand Total</td><td>' . $grand_total . '</td></tr>';
echo '</table>';

// ── Midterm / Final grades ────────────────────────────────────────────────────
$midterm_grade = '';
$final_grade   = '';
if (file_exists($grades_csv)) {
    $fh = fopen($grades_csv, 'r');
    if ($fh !== false) {
        while (($row = fgetcsv($fh)) !== false) {
            if (!empty(trim($row[0])) && trim($row[0]) === $nickname) {
                $midterm_grade = trim($row[1] ?? '');
                $final_grade   = trim($row[2] ?? '');
                break;
            }
        }
        fclose($fh);
    }
}
if ($midterm_grade !== '' || $final_grade !== '') {
    echo '<h2>Grades</h2>';
    echo '<table>';
    if ($midterm_grade !== '') echo '<tr><td>Midterm Grade</td><td style="font-size:1.4em;font-weight:bold">' . h($midterm_grade) . '</td></tr>';
    if ($final_grade   !== '') echo '<tr><td>Final Grade</td><td style="font-size:1.4em;font-weight:bold">'   . h($final_grade)   . '</td></tr>';
    echo '</table>';
}

include __DIR__ . '/nav_bottom.php';
?>
</body></html>
