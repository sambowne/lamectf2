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

$num_discussions = 12;
$saved = false;

// ── Load students from namefile ───────────────────────────────────────────────
$students = [];
if (file_exists($namefile)) {
    $fh = fopen($namefile, 'r');
    if ($fh !== false) {
        while (($row = fgetcsv($fh)) !== false) {
            if (empty(trim($row[0]))) continue;
            $nick  = trim($row[0]);
            $name  = trim($row[1] ?? '');
            $parts = explode(' ', $name);
            $last  = $parts[count($parts) - 1];
            $students[] = ['nick' => $nick, 'name' => $name, 'last' => $last];
        }
        fclose($fh);
    }
}
usort($students, function($a, $b) { return strcasecmp($a['last'] . ' ' . $a['name'], $b['last'] . ' ' . $b['name']); });

// ── Load existing scores ──────────────────────────────────────────────────────
$scores = []; // [nick => [d1..d12]]
if (file_exists($discussions_csv)) {
    $fh = fopen($discussions_csv, 'r');
    if ($fh !== false) {
        while (($row = fgetcsv($fh)) !== false) {
            if (empty(trim($row[0]))) continue;
            $nick = trim($row[0]);
            $vals = [];
            for ($d = 1; $d <= $num_discussions; $d++) {
                $vals[$d] = intval($row[$d] ?? 0);
            }
            $scores[$nick] = $vals;
        }
        fclose($fh);
    }
}

// ── Handle POST: save scores ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_discussions'])) {
    csrf_verify();
    $fh = fopen($discussions_csv, 'w');
    if ($fh !== false) {
        foreach ($students as $s) {
            $row = [$s['nick']];
            for ($d = 1; $d <= $num_discussions; $d++) {
                $val = intval($_POST['d'][$s['nick']][$d] ?? 0);
                $row[] = $val;
                $scores[$s['nick']][$d] = $val;
            }
            fputcsv($fh, $row);
        }
        fclose($fh);
        chmod($discussions_csv, 0600);
        $saved = true;
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Discussion Scores</title>
<style>
  body  { font-family: Arial, sans-serif; margin: 20px; padding: 0; }
  h2   { color: #333; }
  table { border-collapse: collapse; font-size: 0.88em; }
  th, td { border: 1px solid #ccc; padding: 5px 7px; text-align: center; }
  th   { background: #f0f0f0; white-space: nowrap; }
  td.name  { text-align: left; white-space: nowrap; background: #fafafa; }
  td.total { font-weight: bold; background: #e8f4e8; }
  tr.col-total td { font-weight: bold; background: #d0eaff; }
  th.d-col { width: 30px; padding: 3px 2px; }
  td.d-cell { padding: 2px; }
  input.di { width: 28px; padding: 1px 2px; font-size: 0.9em; text-align: center;
             -moz-appearance: textfield; }
  input.di::-webkit-inner-spin-button,
  input.di::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
  .ok  { background: #dfd; border: 1px solid #4a4; padding: 8px 14px; border-radius: 4px; margin: 10px 0; color: #040; }
  input[type=submit] { margin-top: 12px; padding: 8px 24px; font-size: 1em; cursor: pointer; }
</style>
</head>
<body>
<?php echo "<h2 align='center'>" . h(strip_tags($description)) . "</h2>"; ?>
<?php include 'nav.php'; ?>
<h2>Discussion Scores</h2>
<?php if ($saved): ?>
<div class="ok">&#10003; Scores saved.</div>
<?php endif; ?>
<?php if (empty($students)): ?>
<p>No students registered yet. <a href="register_form.php">Register students</a> first.</p>
<?php else: ?>
<form method="post">
<?php echo csrf_field(); ?>
<table>
<tr>
  <th>Name</th>
  <th>Nickname</th>
  <?php for ($d = 1; $d <= $num_discussions; $d++) echo "<th class=\"d-col\">D$d</th>"; ?>
  <th>Total</th>
</tr>
<?php
$col_totals = array_fill(1, $num_discussions, 0);
foreach ($students as $s):
    $nick = $s['nick'];
    $row_total = 0;
    for ($d = 1; $d <= $num_discussions; $d++) {
        $v = $scores[$nick][$d] ?? 0;
        $col_totals[$d] += $v;
        $row_total += $v;
    }
?>
<tr>
  <td class="name"><?php echo h($s['name']); ?></td>
  <td><?php echo h($nick); ?></td>
  <?php for ($d = 1; $d <= $num_discussions; $d++):
      $v = $scores[$nick][$d] ?? 0; ?>
  <td class="d-cell"><input type="number" class="di" min="0"
       name="d[<?php echo h($nick); ?>][<?php echo $d; ?>]"
       value="<?php echo $v; ?>"></td>
  <?php endfor; ?>
  <td class="total"><?php echo $row_total; ?></td>
</tr>
<?php endforeach; ?>
<tr class="col-total">
  <td colspan="2">Column Total</td>
  <?php
  $grand = 0;
  for ($d = 1; $d <= $num_discussions; $d++) {
      echo "<td>" . $col_totals[$d] . "</td>";
      $grand += $col_totals[$d];
  }
  ?>
  <td class="total"><?php echo $grand; ?></td>
</tr>
</table>
<input type="submit" name="save_discussions" value="Save Scores">
</form>
<?php endif; ?>
<?php include __DIR__ . '/nav_bottom.php'; ?>
</body>
</html>
