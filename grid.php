<?php
session_start();
require __DIR__ . '/csrf.php';
include 'config.php';

$authed = !empty($_SESSION['admin_authed']) &&
          !empty($_SESSION['admin_authed_class']) &&
          $_SESSION['admin_authed_class'] === ($course_number ?? '');

if (!$authed) {
    header('Location: admin.php' . $nav_c);
    exit;
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES); }

// ── Load students ────────────────────────────────────────────────────────────
$students = []; // nick => ['name'=>..., 'nick'=>...]
if (file_exists($namefile)) {
    if (($fh = fopen($namefile, 'r')) !== false) {
        while (($row = fgetcsv($fh)) !== false) {
            if (count($row) >= 1) $students[trim($row[0])] = ['name' => trim($row[1] ?? $row[0]), 'nick' => trim($row[0])];
        }
        fclose($fh);
    }
}

// ── Load CTF scores (logfile) ─────────────────────────────────────────────────
// logfile format: nick, chal_id, score, timestamp
$ctf = []; // nick => [chal_id => score]
if (file_exists($logfile)) {
    if (($fh = fopen($logfile, 'r')) !== false) {
        while (($row = fgetcsv($fh)) !== false) {
            if (count($row) < 3) continue;
            [$nick, $chal, $score] = [trim($row[0]), trim($row[1]), (int)$row[2]];
            if (!isset($ctf[$nick][$chal]) || $score > $ctf[$nick][$chal])
                $ctf[$nick][$chal] = $score;
        }
        fclose($fh);
    }
}

// ── Load extra credit (xfile) ────────────────────────────────────────────────
// xfile format: nick, description, score, timestamp
$extra = []; // nick => total_extra
if (file_exists($xfile)) {
    if (($fh = fopen($xfile, 'r')) !== false) {
        while (($row = fgetcsv($fh)) !== false) {
            if (count($row) < 3) continue;
            $extra[trim($row[0])] = ($extra[trim($row[0])] ?? 0) + (int)$row[2];
        }
        fclose($fh);
    }
}

// ── Load discussions ──────────────────────────────────────────────────────────
// discussions_csv format: nick, d1, d2, ..., d12
$disc = []; // nick => [1..12 => score]
$disc_max = 0;
if ($discussions_enabled && file_exists($discussions_csv)) {
    if (($fh = fopen($discussions_csv, 'r')) !== false) {
        while (($row = fgetcsv($fh)) !== false) {
            if (count($row) < 2) continue;
            $nick = trim($row[0]);
            for ($i = 1; $i < count($row); $i++) $disc[$nick][$i] = (int)$row[$i];
            $disc_max = max($disc_max, count($row) - 1);
        }
        fclose($fh);
    }
}
$disc_max = max($disc_max, 12); // always show 12 columns when discussions enabled

// ── Load quiz results (results_csv) ───────────────────────────────────────────
// results_csv format: timestamp, name, nick, quiz_title, score, max
$quiz_scores = [];  // nick => [quiz_title => best_score]
$quiz_titles = [];  // ordered list of unique quiz titles
if (file_exists($results_csv)) {
    if (($fh = fopen($results_csv, 'r')) !== false) {
        while (($row = fgetcsv($fh)) !== false) {
            if (count($row) < 6) continue;
            [, , $nick, $title, $score] = [null, null, trim($row[2]), trim($row[3]), (int)$row[4]];
            if (!in_array($title, $quiz_titles, true)) $quiz_titles[] = $title;
            if (!isset($quiz_scores[$nick][$title]) || $score > $quiz_scores[$nick][$title])
                $quiz_scores[$nick][$title] = $score;
        }
        fclose($fh);
    }
}

// ── Load grades (grades_csv) ──────────────────────────────────────────────────
// grades_csv format: nick, midterm, final
$grades = []; // nick => ['midterm'=>..., 'final'=>...]
if (file_exists($grades_csv)) {
    if (($fh = fopen($grades_csv, 'r')) !== false) {
        while (($row = fgetcsv($fh)) !== false) {
            if (count($row) < 3) continue;
            $grades[trim($row[0])] = ['midterm' => trim($row[1]), 'final' => trim($row[2])];
        }
        fclose($fh);
    }
}

// ── Challenge list (strip LABEL_ entries and BREAK) ──────────────────────────
$chals = [];
foreach ($poss_chals as $c) {
    if (strpos($c, 'LABEL_') === 0 || $c === 'BREAK') continue;
    $chals[] = $c;
}

// ── Save handler ─────────────────────────────────────────────────────────────
$save_ok = '';
$save_err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_grid'])) {
    csrf_verify();

    $new_ctf    = $_POST['ctf']   ?? [];  // [nick][chal] => score
    $new_extra  = $_POST['extra'] ?? [];  // [nick] => score
    $new_disc   = $_POST['disc']  ?? [];  // [nick][d] => score
    $new_quiz   = $_POST['quiz']  ?? [];  // [nick][title_idx] => score
    $new_grades = $_POST['grade'] ?? [];  // [nick] => ['midterm'=>..., 'final'=>...]

    // Rewrite logfile (CTF scores — one row per nick+chal combination, best score)
    $ctf_rows = [];
    foreach ($new_ctf as $nick => $chals_scores) {
        foreach ($chals_scores as $chal => $score) {
            $score = (int)$score;
            if ($score > 0) $ctf_rows[] = [$nick, $chal, $score, date('Y-m-d H:i:s')];
        }
    }
    $ok = true;
    $fh = fopen($logfile, 'w');
    if ($fh === false) { $save_err = "Could not write logfile."; $ok = false; }
    else { foreach ($ctf_rows as $r) fputcsv($fh, $r); fclose($fh); }

    if ($ok) {
        // Rewrite xfile (extra credit — one row per nick, single total entry)
        $fh = fopen($xfile, 'w');
        if ($fh === false) { $save_err = "Could not write xfile."; $ok = false; }
        else {
            foreach ($new_extra as $nick => $score) {
                $score = (int)$score;
                if ($score > 0) fputcsv($fh, [$nick, 'Extra Credit', $score, date('Y-m-d H:i:s')]);
            }
            fclose($fh);
        }
    }

    if ($ok && $discussions_enabled) {
        // Rewrite discussions_csv
        $fh = fopen($discussions_csv, 'w');
        if ($fh === false) { $save_err = "Could not write discussions_csv."; $ok = false; }
        else {
            foreach ($new_disc as $nick => $ds) {
                $row = [$nick];
                for ($i = 1; $i <= $disc_max; $i++) $row[] = (int)($ds[$i] ?? 0);
                fputcsv($fh, $row);
            }
            fclose($fh);
        }
    }

    if ($ok && !empty($quiz_titles)) {
        // Rewrite results_csv (one row per nick+quiz with best score)
        $fh = fopen($results_csv, 'w');
        if ($fh === false) { $save_err = "Could not write results_csv."; $ok = false; }
        else {
            foreach ($new_quiz as $nick => $quiz_scores_new) {
                foreach ($quiz_scores_new as $idx => $score) {
                    $score = (int)$score;
                    $title = $quiz_titles[$idx] ?? '';
                    if ($title !== '') fputcsv($fh, [date('Y-m-d H:i:s'), $nick, $nick, $title, $score, 100]);
                }
            }
            fclose($fh);
        }
    }

    if ($ok) {
        // Rewrite grades_csv
        $fh = fopen($grades_csv, 'w');
        if ($fh === false) { $save_err = "Could not write grades_csv."; $ok = false; }
        else {
            foreach ($new_grades as $nick => $g) {
                fputcsv($fh, [$nick, trim($g['midterm'] ?? ''), trim($g['final'] ?? '')]);
            }
            fclose($fh);
        }
    }

    if ($ok) { $save_ok = 'All changes saved.'; }

    // Reload data after save
    header('Location: grid.php' . $nav_c . '&saved=1');
    exit;
}

if (!empty($_GET['saved'])) $save_ok = 'All changes saved.';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Score Grid</title>
<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2   { margin-bottom: 8px; }
.toolbar { margin: 10px 0 14px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
.toolbar button { padding: 7px 18px; font-size: 0.95em; cursor: pointer; }
.ok  { color:#040; background:#dfd; border:1px solid #4a4; padding:8px 12px; border-radius:4px; margin:8px 0; display:inline-block; }
.err { color:#c00; background:#fdd; border:1px solid #c00; padding:8px 12px; border-radius:4px; margin:8px 0; display:inline-block; }
.wrap { overflow-x: auto; max-width: 100%; border: 1px solid #ccc; border-radius: 5px; }
table { border-collapse: collapse; font-size: 0.82em; white-space: nowrap; }
th, td { padding: 4px 6px; border: 1px solid #ddd; vertical-align: middle; }
th { background: #f0f0f0; position: sticky; top: 0; z-index: 2; font-size: 0.8em; text-align: center; }
td:first-child, th:first-child { position: sticky; left: 0; background: #f8f8f8; z-index: 3; min-width: 120px; }
th:first-child { z-index: 4; }
tr:nth-child(even) td { background: #fafafa; }
tr:nth-child(even) td:first-child { background: #f0f0f0; }
input[type=number] { width: 52px; padding: 2px 4px; font-size: 0.9em; text-align: right; -moz-appearance: textfield; }
input[type=number]::-webkit-inner-spin-button,
input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
input[type=text].grade { width: 38px; padding: 2px 4px; font-size: 0.9em; text-align: center; }
td.total { font-weight: bold; background: #e8f4e8 !important; text-align: right; padding-right: 8px; }
th.grp-ctf   { background: #dde8ff; }
th.grp-extra { background: #ffe8dd; }
th.grp-disc  { background: #e8ffdd; }
th.grp-quiz  { background: #fff8dd; }
th.grp-grade { background: #f0e8ff; }
th.grp-total { background: #d8f0d8; }
</style>
</head>
<body>
<h2><?php echo h($description ?? 'Score Grid'); ?> — Score Grid</h2>

<?php include 'nav.php'; ?>
<?php if ($save_ok): ?><div class="ok">&#10003; <?php echo h($save_ok); ?></div><?php endif; ?>
<?php if ($save_err): ?><div class="err">&#10007; <?php echo h($save_err); ?></div><?php endif; ?>

<form method="post" id="grid-form" action="grid.php<?php echo h($nav_c); ?>">
<?php echo csrf_field(); ?>

<div class="toolbar">
  <button type="button" onclick="sortTable('name')">Sort by Name</button>
  <button type="button" onclick="sortTable('total')">Sort by Total &#9660;</button>
  <button type="submit" name="save_grid" style="background:#2a6;color:#fff;border:none;border-radius:4px">&#128190; Save All</button>
  <a href="admin.php<?php echo h($nav_c); ?>" style="font-size:0.9em">&#8592; Admin</a>
</div>

<div class="wrap">
<table id="score-table">
<thead>
<tr>
  <th>Student</th>
  <?php foreach ($chals as $c): ?><th class="grp-ctf" title="<?php echo h($c); ?>"><?php echo h($c); ?></th><?php endforeach; ?>
  <th class="grp-extra">Extra</th>
  <?php if ($discussions_enabled): ?>
    <?php for ($d = 1; $d <= $disc_max; $d++): ?><th class="grp-disc">D<?php echo $d; ?></th><?php endfor; ?>
  <?php endif; ?>
  <?php foreach ($quiz_titles as $qt): ?><th class="grp-quiz" title="<?php echo h($qt); ?>"><?php echo h(mb_substr($qt, 0, 10) . (mb_strlen($qt) > 10 ? '…' : '')); ?></th><?php endforeach; ?>
  <th class="grp-total">Total</th>
  <th class="grp-grade">Midterm</th>
  <th class="grp-grade">Final</th>
</tr>
</thead>
<tbody id="grid-body">
<?php
// Merge all known nicks from all data sources
$all_nicks = array_unique(array_merge(
    array_keys($students),
    array_keys($ctf),
    array_keys($extra),
    array_keys($disc),
    array_keys($quiz_scores),
    array_keys($grades)
));
sort($all_nicks);
foreach ($all_nicks as $nick):
    $name = $students[$nick]['name'] ?? $nick;
    $total = 0;
    foreach ($chals as $c) $total += (int)($ctf[$nick][$c] ?? 0);
    $total += (int)($extra[$nick] ?? 0);
    if ($discussions_enabled) for ($d = 1; $d <= $disc_max; $d++) $total += (int)($disc[$nick][$d] ?? 0);
    foreach ($quiz_titles as $qt) $total += (int)($quiz_scores[$nick][$qt] ?? 0);
?>
<tr data-name="<?php echo h(strtolower($name)); ?>" data-total="<?php echo $total; ?>">
  <td><?php echo h($name); ?><br><small style="color:#888"><?php echo h($nick); ?></small></td>
  <?php foreach ($chals as $c): ?>
  <td><input type="number" min="0" name="ctf[<?php echo h($nick); ?>][<?php echo h($c); ?>]"
       value="<?php echo (int)($ctf[$nick][$c] ?? 0); ?>" onchange="updateTotal(this)"></td>
  <?php endforeach; ?>
  <td><input type="number" min="0" name="extra[<?php echo h($nick); ?>]"
       value="<?php echo (int)($extra[$nick] ?? 0); ?>" onchange="updateTotal(this)"></td>
  <?php if ($discussions_enabled): ?>
    <?php for ($d = 1; $d <= $disc_max; $d++): ?>
    <td><input type="number" min="0" name="disc[<?php echo h($nick); ?>][<?php echo $d; ?>]"
         value="<?php echo (int)($disc[$nick][$d] ?? 0); ?>" onchange="updateTotal(this)"></td>
    <?php endfor; ?>
  <?php endif; ?>
  <?php foreach ($quiz_titles as $qi => $qt): ?>
  <td><input type="number" min="0" name="quiz[<?php echo h($nick); ?>][<?php echo $qi; ?>]"
       value="<?php echo (int)($quiz_scores[$nick][$qt] ?? 0); ?>" onchange="updateTotal(this)"></td>
  <?php endforeach; ?>
  <td class="total" id="total-<?php echo h($nick); ?>"><?php echo $total; ?></td>
  <td><input type="text" class="grade" name="grade[<?php echo h($nick); ?>][midterm]" maxlength="3"
       value="<?php echo h($grades[$nick]['midterm'] ?? ''); ?>"></td>
  <td><input type="text" class="grade" name="grade[<?php echo h($nick); ?>][final]" maxlength="3"
       value="<?php echo h($grades[$nick]['final'] ?? ''); ?>"></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

</form>

<?php include __DIR__ . '/nav_bottom.php'; ?>

<script>
function updateTotal(input) {
    var row = input.closest('tr');
    var nick = row.querySelector('input[type=number]').name.match(/\[([^\]]+)\]/)[1];
    var inputs = row.querySelectorAll('input[type=number]');
    var total = 0;
    inputs.forEach(function(inp) { total += parseInt(inp.value || '0', 10) || 0; });
    var cell = row.querySelector('td.total');
    if (cell) { cell.textContent = total; row.setAttribute('data-total', total); }
}

function sortTable(by) {
    var tbody = document.getElementById('grid-body');
    var rows = Array.from(tbody.querySelectorAll('tr'));
    if (by === 'name') {
        rows.sort(function(a, b) {
            return a.getAttribute('data-name').localeCompare(b.getAttribute('data-name'));
        });
    } else {
        rows.sort(function(a, b) {
            return parseInt(b.getAttribute('data-total'), 10) - parseInt(a.getAttribute('data-total'), 10);
        });
    }
    rows.forEach(function(r) { tbody.appendChild(r); });
}
</script>
</body>
</html>
