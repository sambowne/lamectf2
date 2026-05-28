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

# v 3.2 Google Badge code moved to grade.php 5-3-26

$verbose = $_REQUEST['verbose'] ?? 0;


# THIS SECTION CHANGES THE LOOK OF THE SCOREBOARD

$version="3.0 with CAPTCHA";

# $border_color = "#efdcff";
# $border_color = "#D783FF";
$border_color = "DeepSkyBlue";
$border_width = "10px";

$bottom_border_color = "#dfccff";

#$solved_color = "#00ff00";
$solved_color = "#ffffff";
# $solved_color_border = "#D783FF";
$solved_color_border = "SpringGreen";

$unsolved_color = "#ffffff";
$unsolved_color_border = "#cccccc";
# $unsolved_color = "#ff0000";

$solved_font_color = "#000000";
$unsolved_font_color = "#000000";
# $unsolved_font_color = "#ffffff";


# Check refresh parameter
if (isset($_GET["refresh"])) { 
	$refresh = "<meta http-equiv='refresh' content='5'>"; 
	}
else { $refresh = ""; }

# Check summary parameter
if (isset($_REQUEST["summary"])) { 
	$summary = 1; 
	}
else { $summary = 0; }




$header = "<html><head><title>Scoreboard</title>";

$header .= "<style>";
$header .= "th, td { border-bottom: 1px solid $bottom_border_color;";
$header .= " margin: 0 10 0px; padding: 0 10 0px; vertical-align: middle; }";

$header .= "td.solved { background-clip: padding-box; padding: 6px; ";
$header .= "border-radius: 13px; background-color: $solved_color; ";
$header .= "border: 5px solid $solved_color_border; ";
$header .= "text-align: center; font-size: 0.7em; ";
$header .= "font-weight: 900; }";

$header .= "td.unsolved { background-clip: padding-box; padding: 6px; ";
$header .= "border-radius: 13px; background-color: $unsolved_color;";
$header .= "border: 5px solid $unsolved_color_border; ";
$header .= "text-align: center; font-size: 0.7em; ";
$header .= "font-weight: bold; }";

$header .= "td.label { border: 0px solid $unsolved_color_border; ";


$header .= "</style>";

$header .= $refresh;

$header .= "</head>";

$header .= "<body bgcolor='#ffffff'  style='font-family:Arial'>";

$solved_prefix = "<td class='solved'><font color='$solved_font_color'>&nbsp;";
$solved_suffix = "&nbsp;</font></td>";

$unsolved_prefix = "<td class='unsolved'><font color='$unsolved_font_color'>&nbsp;";
$unsolved_suffix ="&nbsp;</font></td>";

$label_prefix = "<td class='label'><b>&nbsp;";
$label_suffix = "&nbsp;</b></td>";


# DO NOT CHANGE ANTHING BELOW THIS LINE


# OUTPUT HEADER

print $header;
include 'nav.php';

echo "Summary: $summary ; Verbose: $verbose <p>";



# IMPORT ANSWERS FILE (config.php already loaded at top)
// ── Load grades ───────────────────────────────────────────────────────────────
$grades = []; // [nick => ['midterm' => '', 'final' => '']]
if (file_exists($grades_csv)) {
    $fh = fopen($grades_csv, 'r');
    if ($fh !== false) {
        while (($row = fgetcsv($fh)) !== false) {
            if (empty(trim($row[0]))) continue;
            $grades[trim($row[0])] = ['midterm' => trim($row[1] ?? ''), 'final' => trim($row[2] ?? '')];
        }
        fclose($fh);
    }
}

// ── Save grades on POST ───────────────────────────────────────────────────────
$grades_saved = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_grades'])) {
    csrf_verify();
    $fh = fopen($grades_csv, 'w');
    if ($fh !== false) {
        $mid_post = $_POST['midterm'] ?? [];
        $fin_post = $_POST['final']   ?? [];
        $all_nicks = array_unique(array_merge(array_keys($mid_post), array_keys($fin_post)));
        foreach ($all_nicks as $nick) {
            $mid = preg_replace('/[^A-Za-z+\-]/', '', substr($mid_post[$nick] ?? '', 0, 3));
            $fin = preg_replace('/[^A-Za-z+\-]/', '', substr($fin_post[$nick] ?? '', 0, 3));
            fputcsv($fh, [$nick, $mid, $fin]);
            $grades[$nick] = ['midterm' => $mid, 'final' => $fin];
        }
        fclose($fh);
        chmod($grades_csv, 0600);
        $grades_saved = true;
    }
}

if (! isset($logfile) ) {
	print" Error: logfile not set in config.php";
	exit;
}
if (! isset($xfile) ) {
	print" Error: xfile not set in config.php";
	exit;
}
if (! isset($poss_chals) ) {
	print" Error: poss_chals not set in config.php";
	exit;
}
if (! isset($description) ) {
	print" Error: description not set in config.php";
	exit;
}







if (! isset($removes) ) {
	$removes = "";
	$nremoves = 0;
} else {
	$nremoves = count($removes);
}

if ($verbose > 1) print "<h2>REMOVES: $nremoves, $removes[0] </h2>";

$remove = '_';				# Challenge ID delimiter
$break_mark = "break";		# In config.php;
$label_mark = "label";		# In config.php;
$max_row_length = 20;		# includes break marks


$nposs_chals = count($poss_chals);

if ($verbose>1) {
	print "<p>Nposs_chals: $nposs_chals:<br>";
	print_r($poss_chals);
	print "<p>";
}


# Check showtest parameter
if (isset($_GET["showtest"])) { $showtest = 1; }
else { $showtest = 0; }

# Check challenge parameter
if (isset($_GET["challenge"])) { $challenge = $_GET["challenge"]; }
else { $challenge = ""; }



echo "<h2 align='center'>" . htmlspecialchars(strip_tags($description), ENT_QUOTES) . "</h2>";



print "Reading $namefile<p>";

// Open the file to get existing content
if (!file_exists($namefile)) {
    echo "<div style='background:#ffd;border:1px solid #aa0;padding:12px;border-radius:6px;margin:16px 0'>"
       . "<b>No students registered yet.</b> Scores will appear here once students "
       . "<a href='register_form.php'>register</a>.</div>";
    include __DIR__ . '/nav_bottom.php';
    echo "</body></html>";
    exit;
}
$current = file_get_contents($namefile);
if (!$current) { exit("<h1>Error! Cannot read $namefile!</h1>"); }

if ($verbose > 0) print "<pre>$current</pre>";
$array = str_getcsv ($current);
  
$names_csv = array_map('str_getcsv', file($namefile));
$numlines_names = count($names_csv);
if ($verbose > 1) print "<p>CSV contains $numlines_names lines.<p>\n";
  
if ($verbose > 1) print "<h2>CSV:</h2><pre>";
if ($verbose > 1) print_r($names_csv);
if ($verbose > 1) print "</pre>\n";

print "Found $numlines_names lines<p>";



# READ SCORE LOGS  

# REVISED on 3-25-23 because it kept coming up empty

$csvData = file_get_contents($logfile);
$lines = explode(PHP_EOL, $csvData);
$array = array();
foreach ($lines as $line) {
    $array[] = str_getcsv($line);
}
# print_r($array);

# append xfile into $csv -- XFILE ADDED 3-22-26
# Cleaning xfile code moved to grade.php 5-3-26

# echo "Starting clean_xfile script.<br>";
# $output = shell_exec('php clean_xfile.php');
# echo "Output from clean_xfile script: " . $output . "<br>";
# echo "clean_xfile finished.<br>";


$csv = $array;

#$csv = array_map('str_getcsv', file($logfile));
#$csvx = array_map('str_getcsv', file($xfile));
#$csv = array_merge($csv, $csvx);

$numlines = count($csv);
#$numlinesx = count($csvx);

if ($verbose > 1) {
	print("Logfile: $logfile<br><pre>:");
	print_r(file($logfile));
	print("</pre></p>");
	print "<p>CSV contains $numlines lines.<p>\n";
}
if ($verbose > 1) print "<h2>CSV:</h2><pre>";
if ($verbose > 1) print_r($csv);
if ($verbose > 1) print "</pre>\n";
  
if ($verbose > 1) {
  for( $i = 0; $i<$numlines; $i++ ) { 
    if ( isset($csv[$i][0]) && isset($csv[$i][1]) && isset($csv[$i][2]) ){
  
      print "<b>Log line $i:</b> ". $csv[$i][0] . " " . $csv[$i][1] . " " . $csv[$i][2] . "<br>\n";
    }
  }
}


# Accumulate Scores
$winners = array();
$chals = array();
$unsolved_chals = array();
$scores = array();

for( $i = 0; $i<$numlines; $i++ ) { 
  if ( isset($csv[$i][0]) && isset($csv[$i][1]) && isset($csv[$i][2]) ){
    if ( strlen($csv[$i][0]) >0 && strlen($csv[$i][1]) >0 && strlen($csv[$i][2]) >0 ){
      $w = $csv[$i][0];
      $c = $csv[$i][1];
      $s = $csv[$i][2];

      if ($verbose>1) print "Processing log line $i: $w $c $s<br>\n";

      if ( (($showtest ==0) and ($w == "TESTING")) || ($w == "") ) {
        if ($verbose>1) print "Skipping TESTING<br>\n";
      } else {
        if (in_array($w, $winners)) {
          $key = array_search($w, $winners); 
          if ($verbose>1) print "Name already in winners list at index $key!<br>\n";
          $cold = $chals[$key];
          $sold = $scores[$key];
          if ($verbose>1) print "Old chals = $cold Old score = $sold<br>\n";
          $pos = strpos($cold, $c);
          if ($pos === false) {
            if ($verbose>1) print "New data; appending it to results<br>\n";
            $chals[$key] = $chals[$key] . " " . $c;
            $scores[$key] = $scores[$key] + $s;
          }
          else {
            if ($verbose>1) print "Duplicate data; ignoring it<br>\n";
          }
        }   
        else {
          if ($verbose>1) print "Name is new!  Adding it to winners list!<br>\n";
          array_push($winners, $w);
          array_push($chals, $c);
          array_push($scores, $s);
        }
      }
    }
  }
}

$numwinners = count($winners);

if ($verbose>0) print "Found $numwinners winners<p>";

// Read extra credit totals from xfile (summed per student; all rows use label "Extra")
$extra_totals = [];
if (file_exists($xfile)) {
    $fh = fopen($xfile, 'r');
    if ($fh !== false) {
        while (($row = fgetcsv($fh)) !== false) {
            if (empty(trim($row[0])) || !isset($row[2])) continue;
            $nm = trim($row[0]);
            $extra_totals[$nm] = ($extra_totals[$nm] ?? 0) + intval($row[2]);
        }
        fclose($fh);
    }
}

// Read discussion totals
$discussion_totals_sn = [];
if (!empty($discussions_enabled) && file_exists($discussions_csv)) {
    $fh = fopen($discussions_csv, 'r');
    if ($fh !== false) {
        while (($row = fgetcsv($fh)) !== false) {
            if (empty(trim($row[0]))) continue;
            $nm  = trim($row[0]);
            $sum = 0;
            for ($d = 1; $d <= 12; $d++) $sum += intval($row[$d] ?? 0);
            $discussion_totals_sn[$nm] = $sum;
        }
        fclose($fh);
    }
}

// Read dated logfile for latest submission timestamps
$latest_ts    = [];
$latest_epoch = [];
$logfile_dated = $logfile . 'w-date';
if (file_exists($logfile_dated)) {
    foreach (explode(PHP_EOL, file_get_contents($logfile_dated)) as $dl) {
        $dr = str_getcsv($dl);
        if (count($dr) >= 4 && strlen(trim($dr[0])) > 0) {
            $nm = trim($dr[0]);
            $ep = strtotime(trim($dr[3]));
            if ($ep && (!isset($latest_epoch[$nm]) || $ep > $latest_epoch[$nm])) {
                $latest_epoch[$nm] = $ep;
                $latest_ts[$nm]    = trim($dr[3]);
            }
        }
    }
}
$sort_by = $_GET['sort'] ?? 'name';

if ($numwinners > 0) {
  $qs_name = '?sort=name' . ($verbose ? '&verbose=1' : '');
  $qs_date = '?sort=date' . ($verbose ? '&verbose=1' : '');
  if ($sort_by === 'date') {
      print "<p align='center'>Sort by: <a href='$qs_name'>Name/CRN</a> &nbsp;|&nbsp; <b>Date (most recent first)</b></p>";
  } else {
      print "<p align='center'>Sort by: <b>Name/CRN</b> &nbsp;|&nbsp; <a href='$qs_date'>Date (most recent first)</a></p>";
  }
  if ($grades_saved) echo "<div style='background:#dfd;border:1px solid #4a4;padding:8px 14px;border-radius:4px;margin:10px 0;color:#040'>&#10003; Grades saved.</div>";
  echo "<form method='post'>" . csrf_field();
  print "<table style='border: $border_width solid $border_color; ";
  print "border-radius: 15px; ' ";
  print "cellpadding=0 cellspacing=0 border=0 align='center'> ";
  print "<tr><th align='center'>&nbsp;Sort&nbsp;</th><th align='center'>&nbsp;Name&nbsp;</th><th align='center'>&nbsp;Score&nbsp;</th><th align='center'>&nbsp;Latest Submission&nbsp;</th><th align='center'>&nbsp;Midterm&nbsp;</th><th align='center'>&nbsp;Final&nbsp;</th></tr>";
}





# Build output strings
$outlines = array();

for( $i = 0; $i<$numwinners; $i++ ) { 
  $ci = $chals[$i];
  $chal_list = "";
  $chal_count = 0;
  for( $j=0; $j<$nposs_chals; $j++ ) {
  	$curr_chal = $poss_chals[$j];
    if ($verbose>1) { 
    	print "<p>i, j, ci: $i $j $ci<p>"; 
    	print "<p>curr_chal: $curr_chal<p>";     	
    	}
    
    if (substr(strtolower($curr_chal),0,5) == $label_mark) {
    	$cell_prefix = $label_prefix;
    	$cclean = substr($curr_chal,6);
    	$cell_suffix = $label_suffix;
    	$curr_label = $cclean;
    	if ($verbose>1) { print "<p>Label found: $cclean  <p>"; }
    } else {   	# Not a label
		$pos = strpos($ci, $curr_chal);
      	if ($pos === false) { 						# UNSOLVED
    		$cell_prefix = $unsolved_prefix;
       		$cell_suffix = $unsolved_suffix;
       		$cclean = str_replace($remove, "", $curr_chal);
       		for ( $r = 0; $r <$nremoves; $r++) {
        		$cclean = str_replace($removes[$r], "", $cclean);
       		}

    		if ($verbose>1) { print "<p>Unsolved challenge: $cclean  <p>"; }
    	} 
  		else { 										# SOLVED
    		$cell_prefix = $solved_prefix;
    		$cell_suffix = $solved_suffix;
       		$cclean = str_replace($remove, "", $curr_chal);
       		for ( $r = 0; $r <$nremoves; $r++) {
        		$cclean = str_replace($removes[$r], "", $cclean);
       		}
    		if ($verbose>0) { print "<p>Solved challenge: $cclean by nickname: $winners[$i] <p>"; }
    	}
    }
    
	# ROW TOO LONG
   	if (($chal_count+1) % $max_row_length == 0) { 
   		$chal_list .= "</tr><tr>"; 
   		$chal_count = -1;
   		}

	if ($verbose>1) { print "<p>Trying to add $cclean to chal_list <p>"; }

	# BREAK MARK FOUND
    if (strtolower($curr_chal) == $break_mark) { 		# Break mark

 	  	 if ($challenge == "") {
    	$chal_list .= "</tr><tr>"; 
  	 	 } 
  	 	 
    	$chal_count = -1;
    	if ($verbose>1) { print "<p>Break mark found  <p>"; }
    	}
    else {		# Add non-break challenge numbers to list
 
 	  	 if ($challenge == "") {
 		    $chal_list .= "$cell_prefix$cclean$cell_suffix";
  	 	 } else {
  	 	 	# print "<h2>$challenge $curr_label</h2>";
  	 	 	if ($challenge == $curr_label) {
 		    $chal_list .= "$cell_prefix$cclean$cell_suffix";
  	 	 	}
 	  	 }
    	 if ($verbose>1) { print "<p>Adding to chal_list: $cclean  <p>"; }
    }    

    $chal_count += 1;
  }
  $chal_list .= "</tr>\n";
  if ($verbose>1) { print "\n<h2>$i $chal_list</h2>\n"; }
  
  # Add Kahoot Markers
  for( $j=0; $j<$numlinesx; $j++ ) {
    if ( isset($csvx[$i][0]) && isset($csvx[$i][1]) && isset($csvx[$i][2]) ){
      if ( strlen($csvx[$i][0]) >0 && strlen($csvx[$i][1]) >0 && strlen($csvx[$i][2]) >0 ){
        $namex = $csvx[$j][0];
        $labelx = $csvx[$j][1];
        $ptsx = $csvx[$j][2];

        if ($namex === $winners[$i]) {
          $lclean = str_replace("_", ":", $labelx);
  		  $lclean .= strval($ptsx);
          $chal_list .= "$solved_prefix$lclean$solved_suffix";
        }
      }
  	}  		
  }


  $extra_pts = $extra_totals[$winners[$i]] ?? 0;
  $disc_pts = !empty($discussions_enabled) ? ($discussion_totals_sn[$winners[$i]] ?? 0) : 0;
  $total_pts = $scores[$i] + $extra_pts + $disc_pts;
  $sort = (string) ($total_pts + 10000);
  $n = "<td align='center'><b><big>&nbsp;" . $winners[$i] . "&nbsp;</big></b></td>";
  $s = "<td align='center'><b>&nbsp;&nbsp;&nbsp;" . (string) $total_pts . "&nbsp;&nbsp;&nbsp;</b></td>";

  
  $sort_key = $winners[$i];
  $sort_col = "<td align='center'>" . htmlspecialchars($winners[$i], ENT_QUOTES) . "</td>";

	if ($verbose > 1) print "Finding real name for winner[$i]: $winners[$i]<br>";
	# NEW: ADD REAL NAMES
	for( $iname = 0; $iname<$numlines_names; $iname++ ) {
	  $nickname = $names_csv[$iname][0];
	  $realname = $names_csv[$iname][1];
	  $realid = $names_csv[$iname][2];
	  $crn = $names_csv[$iname][3];
	  $splitname = explode(' ', $realname);
	  $lastname = $splitname[count($splitname) - 1];

	  if ($verbose > 0) print "$iname NickName:$nickname Realname:$realname id:$realid crn:$crn winners[i]: $winners[$i]<br>";

	  if ($winners[$i] == $nickname) {
	    $sort_key = $crn . '_' . $lastname;
	    $sort_col = "<td align='center'>" . htmlspecialchars($crn . '_' . $lastname, ENT_QUOTES) . "</td>";
	    $n = "<td align='center'><b><big>&nbsp;" . htmlspecialchars($realname . ' (' . $winners[$i] . ')', ENT_QUOTES) . "&nbsp;</big></b></td>";
	    if ($verbose > 0) print "MATCH: $winners[$i] = $nickname ; sort_key: $sort_key<br>";
	  }
	}

  $sort_epoch = $latest_epoch[$winners[$i]] ?? 0;
  $ts_display = $latest_ts[$winners[$i]] ?? '—';
  $t = "<td align='center'>" . htmlspecialchars($ts_display, ENT_QUOTES) . "</td>";

  $nick_esc = htmlspecialchars($winners[$i], ENT_QUOTES);
  $mid_val  = htmlspecialchars($grades[$winners[$i]]['midterm'] ?? '', ENT_QUOTES);
  $fin_val  = htmlspecialchars($grades[$winners[$i]]['final']   ?? '', ENT_QUOTES);
  $gm = "<td align='center'><input type='text' name='midterm[$nick_esc]' value='$mid_val' maxlength='3' style='width:48px;text-align:center;font-size:1em'></td>";
  $gf = "<td align='center'><input type='text' name='final[$nick_esc]'   value='$fin_val' maxlength='3' style='width:48px;text-align:center;font-size:1em'></td>";

  if ($verbose > 0) print "pushing sort_key: $sort_key<br>";

  $outlines[] = ['key' => $sort_key, 'epoch' => $sort_epoch, 'html' => $sort_col . $n . $s . $t . $gm . $gf];
}

if ($sort_by === 'date') {
    usort($outlines, function($a, $b) { return $b['epoch'] - $a['epoch']; });
} else {
    usort($outlines, function($a, $b) { return strcmp($a['key'], $b['key']); });
}


# DIAGNOSTIC OUTPUT
if ($verbose > 0) {
  print "<h2>Outlines</h2>";
  for( $i = 0; $i<$numwinners; $i++ ) {
      $cdiag = str_replace("<", "!", $outlines[$i]['html']);
    print $cdiag;
    print "<br>";
  }
}  

# DIAGNOSTIC OUTPUT
if ($verbose > 2) {
  print "<h2>Winners</h2><pre>";
  print_r($winners);
  print "</pre>";
  
  print "<h2>Chals</h2><pre>";
  print_r($chals);
  print "</pre>";
  
  print "<h2>Scores</h2><pre>";
  print_r($scores);
  print "</pre>";  
}  


# Print scores

if ($numwinners < 200) {$numprint = $numwinners; }
else { $numprint = 200; }


for( $i = 0; $i<$numprint; $i++ ) {
    print "<tr>";
    print $outlines[$i]['html'];
    print "</tr>";
}



print "</td></tr></table>";
echo "<p><input type='submit' name='save_grades' value='Save Grades' style='padding:8px 22px;font-size:1em;cursor:pointer'></p>";
echo "</form>";
print "<p>Version: $version <p>";

include __DIR__ . '/nav_bottom.php';
print "</body></html>\n";



# v1.01 removed 'EH1." from challenges string
# v1.02 requires auth parameter
# v1.03 adds column headers
# v1.04 removes "EH", added authorization
# v1.05 removed auth token, moved to content server, implemented showtest as a parameter, 
#       implmented _ terminators
# v1.06 added extra credit xfile
# v1.07 removes "A" from chalnum
# v 1.08 sorts "Challenges Solved" list and uses small tags
# v 1.09 shows challenges remaining
# v 1.10 gathers challenges, separates leaders, skips blank names
# v 1.11 labels Solved and unsolved
# v 1.12 better sorting
# v 1.13 labels kahoots
# v 1.14 string to remove placed in $remove
# v 2.00 moved formatting to strings at top, added rank #
# v 2.01 reads xfile and logfile from config.php
# v 2.02 reads description from config.php
# v 2.03 reads a list of removes from answwers.php and summary
# v 2.04 only prints top 50
# v. 2.05 prints up to 200 scores 10-8-22
# v. 2.10 stops using array_map and uses a loop instead, stops using xfile


?>