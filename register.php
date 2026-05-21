<?php
session_start();
require __DIR__ . '/csrf.php';
csrf_verify();
include 'config.php';

$nickname    = trim($_REQUEST['nickname']    ?? '');
$lastname    = trim($_REQUEST['lastname']    ?? '');
$firstname   = trim($_REQUEST['firstname']  ?? '');
$id          = trim($_REQUEST['id']         ?? '');
$section     = trim($_REQUEST['crn']        ?? '');
$access_code = trim($_REQUEST['access_code'] ?? '');

if ($nickname    === '') { die('<h3>Error: nickname is required.</h3>'); }
if ($lastname    === '') { die('<h3>Error: last name is required.</h3>'); }
if ($firstname   === '') { die('<h3>Error: first name is required.</h3>'); }
if ($id          === '') { die('<h3>Error: student ID is required.</h3>'); }
if ($access_code === '') { die('<h3>Error: access code is required.</h3>'); }

// Store name as "Last, First" so alphabetical sorting works correctly
$name = $lastname . ', ' . $firstname;

if (!isset($namefile)) {
    die('<h3>Error: namefile not set in config.php</h3>');
}

// Reject duplicate nickname
if (file_exists($namefile)) {
    $nf = fopen($namefile, 'r');
    if ($nf !== false) {
        while (($row = fgetcsv($nf)) !== false) {
            if (trim($row[0]) === $nickname) {
                fclose($nf);
                die('<h3>Error: that nickname is already taken. Please go back and choose a different one.</h3>');
            }
        }
        fclose($nf);
    }
}

// Write to student names file
$handle = fopen($namefile, 'a');
if ($handle === false) {
    die('<h3>Error: could not open names file for writing. Check that ~/.lamectf2/ exists and is writable.</h3>');
}
fputcsv($handle, [$nickname, $name, $id, $section]);
fclose($handle);

// Write nickname + access code to nick_access CSV
$afile = fopen($access_codes_csv, 'a');
if ($afile === false) {
    die('<h3>Error: could not open access codes file for writing.</h3>');
}
// Write header if file is new/empty
if (filesize($access_codes_csv) === 0) {
    fputcsv($afile, ['Nickname', 'Access Code']);
}
fputcsv($afile, [$nickname, $access_code]);
fclose($afile);

echo "<h2 align='center'>" . htmlspecialchars(strip_tags($description), ENT_QUOTES) . "</h2>";
echo "<h2>Registration successful!</h2>";
echo "<p>Nickname: <b>" . htmlspecialchars($nickname) . "</b><br>";
echo "Name: <b>" . htmlspecialchars($name) . "</b><br>";
echo "Access code: <b>" . htmlspecialchars($access_code) . "</b> — write this down, you will need it for quizzes.</p>";
echo "<p><a href='login.php" . htmlspecialchars($nav_c, ENT_QUOTES) . "&registered=1'><strong>Log in now &rarr;</strong></a></p>";
?>
