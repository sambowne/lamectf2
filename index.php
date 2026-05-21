<?php
session_start();
require __DIR__ . '/csrf.php';
include 'config.php';
if (empty($password_hash)) {
    header('Location: init.php');
    exit;
}

$_k_nick = 'student_nick_' . ($course_number ?? 'default');
if (empty($_SESSION[$_k_nick])) {
    header('Location: login.php' . $nav_c);
    exit;
}
$_session_nick = $_SESSION[$_k_nick];
?>
<html>
<head>
<title>Enter Flags</title>
<style>
table.flag { padding: 10px; border-radius: 15px; background-color: #ffffff; border: 10px solid #0066ff; }
table.scoreboard { padding: 10px; border-radius: 15px; background-color: #ffffff; border: 10px solid #cccccc; }
</style>

<!-- from https://www.plus2net.com/php_tutorial/list-period.php -->
<script type="text/javascript">
function reload()
{
var val=document.form1.type.value 
var val2=document.form1.name.value 
self.location='index.php?type=' + val + '&name=' + val2;
}
</script>

</head>
<body bgcolor="#ffffff" style="font-family:Arial">


<!-- config.php already included at top -->
<?php

if (! isset($poss_chals) ) {
	print '<h3>Error: poss_chals not set in config.php</h3>';
	exit;
}
$nposs_chals = count($poss_chals);

if (! isset($description) ) {
	print '<h3>Error: description not set in config.php</h3>';
	exit;
}
if (! isset($namefile) ) {
	print '<h3>Error: namefile not set in config.php</h3>';
	exit;
}

$remove = '_';				# Challenge ID delimiter

?>



<!-- Print Header -->
<?php
$header  = "<h2 align='center'>" . htmlspecialchars(strip_tags($description), ENT_QUOTES) . "</h2>";
print $header;
include 'nav.php';

$has_students = file_exists($namefile) && filesize($namefile) > 0;
if (!$has_students) {
    echo '<p style="text-align:center">No students are registered yet. <a href="register_form.php' . htmlspecialchars($nav_c, ENT_QUOTES) . '"><strong>Register now</strong></a> to enable flag submission.</p>';
    include __DIR__ . '/nav_bottom.php';
    echo '</body></html>';
    exit;
}
?>


<!-- Flag Submission Form -->

<form name=form1 action='grade.php' method='post'>
<?php echo csrf_field(); ?>
<input type="hidden" name="c" value="<?php echo htmlspecialchars($course_number ?? '', ENT_QUOTES); ?>">
<blockquote>

<table align='center' class='flag'><tr><td colspan=2><h2 align='center'>Enter Flag</h2></td></tr>

<tr><td>
  <big><b>Series:</b></big></td>
  <td>
  <select name='type' onchange="reload();">
  <?php
  $series = array();
  foreach ($poss_chals as $item) {
      if (strncmp($item, 'LABEL_', 6) === 0) {
          $s = substr($item, 6);
          if (!in_array($s, $series)) $series[] = $s;
      }
  }
  $tp = $_GET['type'] ?? ($series[0] ?? '');
  foreach ($series as $s) {
      $sel = ($tp === $s) ? ' selected' : '';
      echo "<option value='" . htmlspecialchars($s, ENT_QUOTES) . "'$sel>" . htmlspecialchars($s, ENT_QUOTES) . "</option>\n  ";
  }
  ?>
</td></tr>

<tr><td>
  <big><b>Challenge:</b></big></td>
  <td>
   <select name='chalnum'>
   <?php
   // $tp already set from series dropdown above
   for( $j=0; $j<$nposs_chals; $j++ ) {
     $curr_chal = $poss_chals[$j];
     if (strstr($curr_chal, $tp)) {
     	$cclean = str_replace($remove, "", $curr_chal);
     	$mark = substr(strtolower($cclean),0,5);
     	if ( ($mark != "break") && ($mark != "label") ) {
     	   print "<option value='$curr_chal'>$cclean</option>";
     	}
     }
   } ?>
</select> 
</td></tr>

<tr><td><big><b>Name:</b></big></td>
  <td>
  <input type="hidden" name="name" value="<?php echo htmlspecialchars($_session_nick, ENT_QUOTES); ?>">
  <strong><?php echo htmlspecialchars($_session_nick, ENT_QUOTES); ?></strong>
  </td></tr>

<tr><td><big><b>Flag:</b></big></td>
  <td><textarea name='answer' rows='1' cols='25' required></textarea>  
  </td></tr>
<tr><td colspan=2 align='center'><big><b>
<input type='submit' value='Submit'></b></big>
</td></tr>


<tr><td colspan=2 align='center'><br>
<a href="register_form.php">Register new user</a>
</td></tr>


</table>

</blockquote>
</form>

<p>




<?php include __DIR__ . '/nav_bottom.php'; ?>
</body>
</html>



