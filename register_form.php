<?php session_start(); require __DIR__ . '/csrf.php'; include 'config.php'; ?>
<html>
<head>
<title>Register</title>
<style>
table.flag { padding: 10px; border-radius: 15px; background-color: #ffffff; border: 10px solid #0066ff; }
button.regen { margin-left: 8px; padding: 4px 10px; font-size: 0.9em; cursor: pointer; }
small.hint { color: #555; display: block; margin-top: 3px; }
</style>
</head>
<body bgcolor="#ffffff" style="font-family:Arial">
<?php echo "<h2 align='center'>" . htmlspecialchars(strip_tags($description ?? ''), ENT_QUOTES) . "</h2>"; ?>
<form name=form1 action='register.php' method='post'>
<?php echo csrf_field(); ?>
<input type="hidden" name="c" value="<?php echo htmlspecialchars($course_number ?? '', ENT_QUOTES); ?>">
<blockquote>

<table align='center' class='flag'>
<tr><td colspan=2><h2 align='center'>Register</h2></td></tr>

<tr><td><big><b>Nickname:</b></big></td>
  <td><input type='text' name='nickname' size='45' minlength="2" required></td></tr>

<tr><td><big><b>Last Name:</b></big></td>
  <td><input type='text' name='lastname' size='45' minlength="2" required></td></tr>

<tr><td><big><b>First Name:</b></big></td>
  <td><input type='text' name='firstname' size='45' minlength="2" required></td></tr>

<tr><td><big><b>Student ID:</b></big></td>
  <td><input type='text' name='id' size='45' minlength="4" required></td></tr>

<?php if ($ask_section ?? true): ?>
<tr><td><big><b>Section Number:</b></big></td>
  <td><input type='text' name='crn' size='20'></td></tr>
<?php endif; ?>

<tr><td><big><b>Access Code:</b></big></td>
  <td>
    <input type='text' name='access_code' id='access_code' size='30' minlength="4" required>
    <button type='button' class='regen' onclick='generateCode()'>New Code</button>
    <small class='hint'>You will use this code to take quizzes. Write it down.</small>
  </td></tr>

<tr><td colspan=2 align='center'><big><b>
<input type='submit' value='Register'>
</b></big></td></tr>
</table>

</blockquote>
</form>

<script>
var words = [
  'apple','ocean','forest','river','thunder','silver','golden','crystal',
  'swift','brave','cedar','maple','falcon','arrow','ember','glacier',
  'harbor','jungle','lantern','meadow','orbit','pebble','quarry','riddle',
  'saddle','tundra','valley','willow','zenith','anchor','blossom','cobalt'
];
function randomWord() { return words[Math.floor(Math.random() * words.length)]; }
function generateCode() {
  document.getElementById('access_code').value = randomWord() + '_' + randomWord();
}
generateCode();
</script>

</body>
</html>
