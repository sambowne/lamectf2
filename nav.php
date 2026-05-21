<?php
if (!isset($nav_c))  $nav_c  = '?';
if (!isset($nav_qs)) $nav_qs = '';
if (!isset($projects_url)) $projects_url = '';
$_nc = htmlspecialchars($nav_c, ENT_QUOTES);

$_nav_student = '';
if (session_status() === PHP_SESSION_ACTIVE && !empty($course_number)) {
    $_sk = 'student_nick_' . $course_number;
    if (!empty($_SESSION[$_sk])) {
        $_nav_student = '<span style="font-size:0.9em;color:#555">Logged in as: <strong>'
            . htmlspecialchars($_SESSION[$_sk], ENT_QUOTES)
            . '</strong> &nbsp;<a href="login.php' . $_nc . '&logout=1">Log out</a></span> &nbsp;|&nbsp; ';
    }
}
?>
<nav style="margin-bottom:20px; padding:10px; background:#f5f5f5; border-radius:6px; text-align:center;">
  <?php echo $_nav_student; ?>
  <a href="scoreboard_overall.php<?php echo $_nc; ?>"><b>Class Scoreboard</b></a> &nbsp;|&nbsp;
  <a href="scoreboard_with_quizzes.php<?php echo $_nc; ?>">My Scores</a> &nbsp;|&nbsp;
  <a href="index.php<?php echo $_nc; ?>">Submit Flags</a> &nbsp;|&nbsp;
  <a href="quiz.php<?php echo $_nc; ?>">Take a Quiz</a><?php if (!empty($projects_url)): ?> &nbsp;|&nbsp;
  <a href="<?php echo htmlspecialchars($projects_url, ENT_QUOTES); ?>">Projects</a><?php endif; ?>
</nav>
