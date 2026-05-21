<?php
// Class selector landing page — does NOT include config.php (avoids redirect loop)
session_start();

$_secret_dir = ($_SERVER['HOME'] ?? posix_getpwuid(posix_geteuid())['dir']) . '/.lamectf2';

// Discover configured classes
$_classes = [];
foreach (glob($_secret_dir . '/*_secret.php') ?: [] as $_f) {
    if (!preg_match('/\/([A-Za-z0-9_-]+)_secret\.php$/', $_f, $_m)) continue;
    $_cn = $_m[1];
    // Peek at description
    $_desc = $_cn;
    $_src = file_get_contents($_f);
    if (preg_match('/\$description\s*=\s*([\'"])(.*?)\1/', $_src, $_dm)) $_desc = $_dm[2];
    $_classes[] = ['course' => $_cn, 'desc' => $_desc];
}

function h($s) { return htmlspecialchars($s, ENT_QUOTES); }
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Select Class</title>
<style>
  body { font-family: Arial, sans-serif; max-width: 600px; margin: 60px auto; padding: 0 24px; }
  h1 { color: #333; }
  .class-list { list-style: none; padding: 0; margin: 24px 0; }
  .class-list li { margin: 10px 0; }
  .class-list a {
    display: block; padding: 14px 20px; background: #f0f4ff;
    border: 1px solid #99b; border-radius: 6px; text-decoration: none;
    color: #222; font-size: 1.1em;
  }
  .class-list a:hover { background: #dde8ff; }
  .course-id { font-size: 0.85em; color: #666; margin-left: 8px; }
  .none { color: #888; font-style: italic; }
</style>
</head>
<body>
<h1>Select Your Class</h1>
<?php if (empty($_classes)): ?>
  <p class="none">No classes have been configured yet.</p>
  <p><a href="init.php">Set up your first class &rarr;</a></p>
<?php else: ?>
  <ul class="class-list">
  <?php foreach ($_classes as $_cl): ?>
    <li>
      <a href="index.php?c=<?php echo urlencode($_cl['course']); ?>">
        <?php echo h($_cl['desc']); ?>
        <span class="course-id">(<?php echo h($_cl['course']); ?>)</span>
      </a>
    </li>
  <?php endforeach; ?>
  </ul>
  <p style="font-size:0.9em;color:#555">
    Admin? <a href="init.php">Manage classes</a> &nbsp;|&nbsp;
    <a href="init.php?new=1">Add a new class</a>
  </p>
<?php endif; ?>
</body>
</html>
