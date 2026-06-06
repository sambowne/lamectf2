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

$secret_dir = ($_SERVER['HOME'] ?? posix_getpwuid(posix_geteuid())['dir']) . '/.lamectf2';
$ok  = [];
$err = [];

// ── Handle uploads ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if (isset($_POST['use_example'])) {
        $src  = __DIR__ . '/quiz_example.txt';
        $dest = $secret_dir . '/quiz_example.txt';
        if (file_exists($src) && copy($src, $dest)) {
            chmod($dest, 0600);
            $ok[] = 'Example quiz file installed: quiz_example.txt';
        } else {
            $err[] = 'Could not copy quiz_example.txt';
        }
    }

    if (!empty($_FILES['quiz_files']['name'][0])) {
        foreach ($_FILES['quiz_files']['name'] as $i => $fname) {
            if ($_FILES['quiz_files']['error'][$i] !== UPLOAD_ERR_OK || $fname === '') continue;
            $safe = preg_replace('/[^a-zA-Z0-9._\-]/', '_', basename($fname));
            if (!preg_match('/\.(txt|php)$/i', $safe)) $safe .= '.txt';
            $dest = $secret_dir . '/' . $safe;
            if (move_uploaded_file($_FILES['quiz_files']['tmp_name'][$i], $dest)) {
                chmod($dest, 0600);
                $ok[] = 'Saved: ' . $safe;
            } else {
                $err[] = 'Failed to save: ' . $safe;
            }
        }
    }
}

// ── List current quiz files ───────────────────────────────────────────────────
$current_quiz_files = [];
foreach ($quiz_files as $qf) {
    if (file_exists($qf)) $current_quiz_files[] = $qf;
}
// Also show any other .txt files in secret_dir that aren't in $quiz_files
foreach (glob($secret_dir . '/*.txt') ?: [] as $f) {
    if (!in_array($f, $current_quiz_files, true)) $current_quiz_files[] = $f;
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Quiz Files</title>
<style>
  body  { font-family: Arial, sans-serif; max-width: 760px; margin: 30px auto; padding: 0 20px; }
  .box  { background: #f8f8f8; border: 1px solid #ccc; padding: 18px 20px; border-radius: 6px; margin: 18px 0; }
  .box h2 { margin: 0 0 12px; font-size: 1.1em; }
  .ok   { color: #040; background: #dfd; border: 1px solid #4a4; padding: 8px 12px; border-radius: 4px; margin: 6px 0; }
  .err  { color: #c00; background: #fdd; border: 1px solid #c00; padding: 8px 12px; border-radius: 4px; margin: 6px 0; }
  pre   { background: #222; color: #eee; padding: 14px; border-radius: 5px; overflow-x: auto; font-size: 0.84em; line-height: 1.5; margin: 8px 0 0; }
  label { display: block; margin: 8px 0 4px; font-weight: bold; }
  input[type=file]   { font-size: 1em; }
  input[type=submit] { margin-top: 12px; padding: 8px 22px; font-size: 1em; cursor: pointer; }
  ul.file-list { margin: 6px 0; padding: 0 0 0 20px; }
  ul.file-list li { margin: 3px 0; font-size: 0.95em; }
</style>
</head>
<body>
<?php echo "<h2 align='center'>" . htmlspecialchars(strip_tags($description ?? ''), ENT_QUOTES) . "</h2>"; ?>
<?php include 'nav.php'; ?>

<p><a href="admin.php<?php echo h($nav_c); ?>">&larr; Admin</a></p>

<?php foreach ($ok  as $m): ?><div class="ok">&#10003; <?php echo h($m); ?></div><?php endforeach; ?>
<?php foreach ($err as $e): ?><div class="err">&#10007; <?php echo h($e); ?></div><?php endforeach; ?>

<div class="box">
  <h2>Current Quiz Files</h2>
  <?php if (empty($current_quiz_files)): ?>
  <p>No quiz files found in <code><?php echo h($secret_dir); ?></code>.</p>
  <?php else: ?>
  <ul class="file-list">
    <?php foreach ($current_quiz_files as $qf): ?>
    <li><code><?php echo h(basename($qf)); ?></code></li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
  <p><small><strong>Note:</strong> By default, all <code>.txt</code> files in <code><?php echo h($secret_dir); ?></code>
  are shown to students — including files uploaded for other courses. To restrict this course to specific quizzes,
  add a <code>$quiz_files</code> array to <code><?php echo h($secret_dir . '/' . ($course_number ?? '') . '_secret.php'); ?></code>, for example:<br>
  <code>$quiz_files = ['<?php echo h($secret_dir); ?>/Quiz01_EH_F26.txt', '<?php echo h($secret_dir); ?>/Quiz02_EH_F26.txt'];</code><br>
  When <code>$quiz_files</code> is set there, auto-discovery is skipped and only those files appear.</small></p>
</div>

<div class="box">
  <h2>Upload Quiz File</h2>
  <p>The <strong>first answer listed under each question is correct</strong>; the rest are distractors shown in random order.</p>
  <pre>Title: Example Quiz 8 pts
Select: 2
Points per question: 4
Number of Attempts: 2
Instructions: Read each question carefully. Use the textbook if needed. <a href="https://example.com">Reference</a>

1 Which of the following is a compiled language?

C
Python
JavaScript
Ruby

2 What does HTML stand for?

HyperText Markup Language
HighLevel Text Manipulation Language
HyperText Management Language
HyperLink and Text Markup Language</pre>
  <p><small><code>Select: 2</code> — pick 2 questions at random per attempt &nbsp;|&nbsp;
     <code>Number of Attempts: 2</code> — each student may submit twice &nbsp;|&nbsp;
     <code>Instructions:</code> — optional; displayed above the quiz and may contain HTML (e.g. <code>&lt;a href&gt;</code> links)</small></p>
  <form method="post" enctype="multipart/form-data" action="quiz_upload.php<?php echo h($nav_c); ?>">
    <?php echo csrf_field(); ?>
    <label for="quiz_files">Select quiz file(s) (.txt):</label>
    <input type="file" name="quiz_files[]" id="quiz_files" multiple accept=".txt">
    <input type="submit" value="Upload Quiz Files">
  </form>
  <form method="post" style="margin-top:10px" action="quiz_upload.php<?php echo h($nav_c); ?>">
    <?php echo csrf_field(); ?>
    <input type="submit" name="use_example" value="Install Example Quiz File">
    <small> — installs the built-in <code>quiz_example.txt</code></small>
  </form>
</div>

<?php include __DIR__ . '/nav_bottom.php'; ?>
</body>
</html>
