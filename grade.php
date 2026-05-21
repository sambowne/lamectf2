<?php
session_start();
require __DIR__ . '/csrf.php';
csrf_verify();

$version = "1.10";
$verbose = 0;

function textile_sanitize($string){
	$string = preg_replace('/</', '_', $string);
	$string = preg_replace('/\|/', '_', $string);
	$string = preg_replace('/\*/', '_', $string);
	$string = preg_replace('/\+/', '_', $string);
    $whitelist = '/[^a-zA-Z0-9 \.\>\/\:\!\?_-]/';
    return preg_replace($whitelist, '', $string);
}

$chalnum = textile_sanitize($_REQUEST['chalnum']);
$answer  = textile_sanitize($_REQUEST['answer']);

include 'config.php';

// Always use session nick — POST name is ignored to prevent impersonation.
if (empty($course_number) || empty($_SESSION['student_nick_' . $course_number])) {
    http_response_code(403);
    echo '<!DOCTYPE html><html><body><h2>Not logged in.</h2><p><a href="login.php">Log in</a></p></body></html>';
    exit;
}
$name = $_SESSION['student_nick_' . $course_number];

if (!isset($logfile)) {
    exit("Error: logfile not set in config.php");
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Flag Result</title>
<style>
  body  { font-family: Arial, sans-serif; max-width: 700px; margin: 30px auto; padding: 0 20px; }
  .info { background: #f0f4ff; border: 1px solid #99b; padding: 12px 18px; border-radius: 6px; margin: 16px 0; }
  .info td { padding: 4px 12px 4px 0; }
  .result-correct   { background: #e6ffe6; border: 3px solid #4a4; padding: 18px 24px; border-radius: 8px; margin: 20px 0; }
  .result-incorrect { background: #ffe6e6; border: 3px solid #c44; padding: 18px 24px; border-radius: 8px; margin: 20px 0; }
  .result-correct h2   { color: #060; margin: 0 0 8px; }
  .result-incorrect h2 { color: #900; margin: 0 0 8px; }
  .error { background: #fdd; border: 1px solid #c00; padding: 12px 18px; border-radius: 6px; margin: 16px 0; color: #900; }
  .note  { color: #555; font-size: 0.9em; margin-top: 8px; }
</style>
</head>
<body>
<?php
echo "<h2 align='center'>" . htmlspecialchars(strip_tags($description), ENT_QUOTES) . "</h2>";
include 'nav.php';

echo "<div class='info'><table>";
echo "<tr><td><b>Challenge:</b></td><td>" . htmlspecialchars($chalnum) . "</td></tr>";
echo "<tr><td><b>Name:</b></td><td>"      . htmlspecialchars($name)    . "</td></tr>";
echo "<tr><td><b>Answer:</b></td><td>"    . htmlspecialchars($answer)  . "</td></tr>";
echo "</table></div>\n";




date_default_timezone_set('america/los_angeles');



// CHECK TO SEE IF THE CHALLENGE IS IN THIS CTF
if (!in_array($chalnum, $poss_chals)) {
    echo "<div class='error'><b>Error:</b> Challenge not in this CTF.</div>";
    include __DIR__ . '/nav_bottom.php';
    echo "</body></html>";
    exit;
}
	
 




// FIND CHALLENGE, IF POSSIBLE
$found = 0;
foreach ($correct_answers as $ca) {
  $pchal = $ca[0];
  $pcorrect = $ca[1];
  if ($verbose > 1) {
    print "Comparing to Chal $pchal Correct: <b>$pcorrect</b><br>";
  }
  if ($pchal == $chalnum) {
    $correct = $ca[1];
    $pts = $ca[2];
    $found = 1;
    if ($verbose > 1) {
      print "Challenge found! $pchal Correct: <b>$correct</b> for $pts<br>";
    }
  }
  	
}

if ($found == 0) {
    echo "<div class='error'><b>Error:</b> Challenge not found — check config.php.</div>";
    include __DIR__ . '/nav_bottom.php';
    echo "</body></html>";
    exit;
}





# Google Badge Code added 7-5-23
if ($chalnum == "_GL Badges_") {
	print("Google Badge Submission Detected!<p>");
	
	$url = "https://www.cloudskillsboost.google/public_profiles/" . $answer;

	print("Loading $url <p>");
	
	# $curl = curl_init($url);
	# curl_setopt($curl, CURLOPT_URL, $url);
	# curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

	# $resp = curl_exec($curl);
	# curl_close($curl);
	
	$resp = file_get_contents($url);
	
	# print_r($resp);

	# $lines = explode(PHP_EOL, $resp);
	
	# preg_match('/\<img\ alt\=\"Badge\ for/', $resp, $badge_lines);
	# $badge_lines = preg_grep('/Badge/', $lines);
	# $badge_lines = preg_grep('/\<img\ alt\=\"Badge\ for/', $lines);
	
	
	# CHanged 5-16-25
	# $badge_lines = preg_grep('/.*earned .*/', $lines);
	
	# $num_badges = count($badge_lines);
	
	$num_badges = substr_count($resp, "badge-");
	print("$num_badges Google Badges Found!<p>");

	foreach($badge_lines as $line) {
		$badge_name = strstr($line, "alt=");
		$badge_name = substr($badge_name, 5);
		$name_end = strpos($badge_name, '"');
		$badge_name = substr($badge_name, 0, $name_end);
		print("$badge_name <br>");
	}
	
	$score = 15 * $num_badges;
	print("$score points earned!<p>");

    if ($score > 0) {
	  // Append a new line to the xfile
	  // $current .= "$name,$chalnum,$pts\n";
	  
	  if (! file_put_contents($xfile, "$name,$chalnum,$score\n", FILE_APPEND | LOCK_EX)) 
	    echo "<h1>CANNOT WRITE SCORE to $xfile</h1>";
	  
	    $date = date('m/d/Y h:i:s a', time());
	   if (! file_put_contents(($logfile . "w-date"), "$name,$chalnum,$score, $date\n", FILE_APPEND | LOCK_EX)) 
	    exit("<h1>CANNOT WRITE SCORE to " . $logfile  . "w-date</h1>");
	
		# Dedup the xfile
	
		$xfile_dedup = $xfile . "_dedup";
	
		$csvData = file_get_contents($xfile);
		$lines = explode(PHP_EOL, $csvData);
		$numlines = count($lines);
		sort($lines);
		
		$name_and_chals = array();
		$scores = array();
		foreach ($lines as $line) {
			if ( strlen($line) > 2 ) { 					# skip blank lines
				$last_comma = strrpos($line, ",");
				$scores[] = intval( substr($line, $last_comma+1) );
				$name_and_chals[] = substr($line, 0, $last_comma);
				if ($verbose > 1) {
					print("Preprocessing $xfile line: $line<br>Name+Chal: " . substr($line, 0, $last_comma) . 
					   " score: " . substr($line, $last_comma+1) . "<p>");
				}
			}
		}
	
	
		# Calculate and write dedup file
		if ( (file_put_contents($xfile_dedup, '')) === false)  
		    echo "<h1>CANNOT EMPTY FILE $xfile_dedup</h1>";
		
		$unique_name_and_chals = array_unique($name_and_chals);
		$xclean = array();
		foreach ($unique_name_and_chals as $name) {
			if ($verbose > 1) {
				print("unique_name_and_chals item: $name <br>");
			}
			$max_score = 0;
			for( $i = 0; $i<$numlines; $i++ ) { 
				if ($verbose > 1) {
					print("i, name, name_and_chals: $i, $name, $name_and_chals[$i]<br>");
				}
				if ($name_and_chals[$i] == $name) {
					if ($scores[$i] > max_score) {
						$max_score = $scores[$i];
					}
				}
			}
		
		
		  if (! file_put_contents($xfile_dedup, "$name,$max_score\n", FILE_APPEND | LOCK_EX)) 
		    echo "<h1>CANNOT WRITE SCORE to $xfile_dedup</h1>";
		} 
		
		# Copy dedup file onto orig xfile		
		if (copy($xfile_dedup, $xfile)) {
		    echo "File copied successfully, overwriting if necessary.";
		} else {
		    echo "Failed to copy the file.";
		}
    }
}








// $correct = "foo";

$a = strtolower($answer);
$c = strtolower($correct);

if ($verbose > 0){ print "<p><small>A:$a C:$c</small></p>"; }


// CHECK FOR DOUBLE ANSWER, like DOUBLE_left_right

$pos1 = strpos($c, "double_");
if ($pos1 === false) { $double = false; }
else { 
   $double = true; 
   // DOUBLE
   $pos1 = strpos($c, "_");
   $pos2 = strpos($c, "_", $pos1+1);
   $c1 = substr($c, $pos1+1, $pos2-$pos1-1);
   $c2 = substr($c, $pos2+1);
   if ($verbose > 0) { print "<p><small>pos1:$pos1 pos2:$pos2</small></p>"; }
   if ($verbose > 0) { print "<p><small>c1:$c1 c2:$c2</small></p>"; }
   
   $win = 0;
   $pos = strpos($a, $c1);
   if ($pos === false) {  }
   else { 
   	   $win = 1; 
   	   if ($verbose > 0) { print "<p><small>WIN by matching first answer</small></p>"; }
   	   }
   
   $pos = strpos($a, $c2);
   if ($pos === false) {  }
   else { 
   	   $win = 1; 
   	   if ($verbose > 0) { print "<p><small>WIN by matching second answer</small></p>"; }
   	   }
   
   }
   
// CHECK FOR RANGE ANSWER, like RANGE_1_4

$pos1 = strpos($c, "range_");
if ($pos1 === false) { 
	$range = false; 
  	if ($verbose > 0) { print "<p><small>Not a range answer</small></p>"; }
	}
else { 
  // RANGE
   $range = true; 
   if ($verbose > 0) { print "<p><small>This is a range answer!</small></p>"; }
   $pos1 = strpos($c, "_");
   $pos2 = strpos($c, "_", $pos1+1);
   $c1 = substr($c, $pos1+1, $pos2-$pos1-1);
   $c2 = substr($c, $pos2+1);
   if ($verbose > 0) { print "<p><small>pos1:$pos1 pos2:$pos2</small></p>"; }
   if ($verbose > 0) { print "<p><small>c1:$c1 c2:$c2</small></p>"; }
   if ($verbose > 0) { 
   	print "<p>Intvals: ";
   	print intval($c);
   	print intval($c1);
   	print intval($c2); }
   
   $win = 0;
   if ( (intval($a) >= intval($c1)) and (intval($a) <= intval($c2)) ) {
   	   $win = 1; 
   	   if ($verbose > 0) { print "<p><small>WIN because $a is between $c1 and $c2</small></p>"; }
   	   }
   }
 
if ( (! $double) and (! $range) ) {
   $pos = strpos($a, $c);
   if ($pos === false) { $win = 0; }
   else { $win = 1; }
   }



// $pos = strpos($a, $c);

if ($win == 0) {
    echo "<div class='result-incorrect'><h2>Answer Incorrect</h2>";
    echo "<p class='note'>Click your browser's Back button to try again.</p></div>";
} else {
    echo "<div class='result-correct'><h2>Answer Correct!</h2>";
    if (!file_put_contents($logfile, "$name,$chalnum,$pts\n", FILE_APPEND | LOCK_EX))
        echo "<p class='error'>Cannot write score to $logfile</p>";
    $date = date('m/d/Y h:i:s a', time());
    if (!file_put_contents($logfile . "w-date", "$name,$chalnum,$pts, $date\n", FILE_APPEND | LOCK_EX))
        echo "<p class='error'>Cannot write score to " . $logfile . "w-date</p>";
    echo "<p><b>$pts points</b> recorded for <b>" . htmlspecialchars($name) . "</b>.</p>";
    echo "</div>";
}

echo "<p><small>Version: $version</small></p>";
?>
<?php include __DIR__ . '/nav_bottom.php'; ?>
</body>
</html>