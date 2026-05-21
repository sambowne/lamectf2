<?php
// Bottom navigation bar — include just before </body> on every page.
if (!isset($nav_c)) $nav_c = '?';
$_nc = htmlspecialchars($nav_c, ENT_QUOTES);

// Hide admin-oriented bottom nav when a student is logged in
$_student_logged_in = session_status() === PHP_SESSION_ACTIVE
    && !empty($course_number)
    && !empty($_SESSION['student_nick_' . $course_number]);
if (!$_student_logged_in):
?>
<nav style="margin-top:30px; padding:10px; background:#f0f0f0; border-radius:6px; text-align:center; font-size:0.9em;">
  <a href="scoreboard.php<?php echo $_nc; ?>&summary=1&refresh=1">Live Scoreboard</a> &nbsp;|&nbsp;
  <a href="tail2.php<?php echo $_nc; ?>&count=20&seconds=10&refresh=1">Recent CTF Scores</a> &nbsp;|&nbsp;
  <a href="admin.php<?php echo $_nc; ?>">Admin</a>
</nav>
<?php endif; ?>
