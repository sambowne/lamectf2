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
?>
<html>
<head>
<title>Extra Credit</title>
<link rel="stylesheet" type="text/css" href="https://samsclass.info/style.css">
</head>
<body bgcolor="#ffffff" style="font-family:Arial">

<?php
echo "<h2 align='center'>" . htmlspecialchars(strip_tags($description), ENT_QUOTES) . "</h2>";
include 'nav.php';

// STEP 2: Save extra credit entries
if (isset($_POST['submit_extra'])) {
    csrf_verify();
    $nicks    = $_POST['nick'];
    $points   = $_POST['points'];
    $comments = $_POST['comments'] ?? [];
    $added    = 0;

    foreach ($nicks as $i => $nick) {
        $pts     = trim($points[$i]);
        $comment = preg_replace('/[\r\n,]/', ' ', trim($comments[$i] ?? ''));
        if ($pts !== '' && ctype_digit($pts) && intval($pts) > 0) {
            file_put_contents($xfile, $nick . ",Extra," . intval($pts) . "," . $comment . "\n", FILE_APPEND | LOCK_EX);
            $added++;
        }
    }

    print "<h3>Saved $added extra credit " . ($added == 1 ? "entry" : "entries") . ".</h3>";
    print "<p><a href='extra.php'>Record More Extra Credit</a></p>";
    include __DIR__ . '/nav_bottom.php';
    print "</body></html>";
    exit;
}

// Load student list
$students = array();
if (file_exists($namefile)) {
    $lines = file($namefile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $parts = str_getcsv($line);
        if (count($parts) >= 2) {
            $nick = trim($parts[0]);
            $name = trim($parts[1]);
            if ($nick !== '') {
                $students[] = array('nick' => $nick, 'name' => $name);
            }
        }
    }
} else {
    print "<h3 style='color:orange'>Warning: Student file not found at " . htmlspecialchars($namefile) . "</h3>";
}

usort($students, function($a, $b) { return strcasecmp($a['name'], $b['name']); });

// Load existing extra credit from xfile
$existing = array();
if (file_exists($xfile)) {
    $xlines = file($xfile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($xlines as $xl) {
        $xp = str_getcsv($xl);
        if (count($xp) >= 3) {
            $xnick    = trim($xp[0]);
            $xtype    = trim($xp[1]);
            $xpts     = intval($xp[2]);
            $xcomment = trim($xp[3] ?? '');
            if ($xnick !== '') {
                $existing[$xnick][] = array('pts' => $xpts, 'type' => $xtype, 'comment' => $xcomment);
            }
        }
    }
}

print "<h2 align='center'>Record Extra Credit</h2>";
print "<form action='extra.php' method='post'>";
print csrf_field();
print "<table align='center' class='DeepSkyBlue'>";
print "<tr><th>&nbsp;Nickname&nbsp;</th><th>&nbsp;Real Name&nbsp;</th><th>&nbsp;Previous Extra Credit&nbsp;</th><th>&nbsp;Add Points&nbsp;</th><th>&nbsp;Comment&nbsp;</th></tr>";
foreach ($students as $s) {
    $nick  = htmlspecialchars($s['nick']);
    $name  = htmlspecialchars($s['name']);
    $prev  = '';
    $total = 0;
    if (!empty($existing[$s['nick']])) {
        $parts = array();
        foreach ($existing[$s['nick']] as $e) {
            $line = '<b>' . $e['pts'] . ' pts</b>';
            if ($e['comment'] !== '') $line .= ' &mdash; ' . htmlspecialchars($e['comment']);
            $parts[] = $line;
            $total  += $e['pts'];
        }
        $prev = implode('<br>', $parts) . '<br><b>Total: ' . $total . ' pts</b>';
    } else {
        $prev = '<span style="color:#aaa">none</span>';
    }
    print "<tr>";
    print "<td><input type='hidden' name='nick[]' value='$nick'>$nick</td>";
    print "<td>$name</td>";
    print "<td>$prev</td>";
    print "<td align='center'><input type='number' name='points[]' min='1' size='6' value='' placeholder='pts'></td>";
    print "<td><input type='text' name='comments[]' size='20' value='' placeholder='optional'></td>";
    print "</tr>\n";
}
print "<tr><td colspan=4 align='center'><br>";
print "<input type='submit' name='submit_extra' value='Save Extra Credit'><br><br></td></tr>";
print "</table></form>";
?>
<?php include __DIR__ . '/nav_bottom.php'; ?>
</body>
</html>
