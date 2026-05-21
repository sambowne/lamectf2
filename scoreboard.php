<?php

$verbose = 0;

# THIS SECTION CHANGES THE LOOK OF THE SCOREBOARD

$version="2.11 implements xfile for extra credit 7-5-23";

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
	$refresh = "<meta http-equiv='refresh' content='10'>";
	}
else { $refresh = ""; }

# Check summary parameter
if (isset($_GET["summary"])) { 
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


# IMPORT ANSWERS FILE
include 'config.php';
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



# OUTPUT HEADER

print $header;
echo "<h2 align='center'>" . htmlspecialchars(strip_tags($description), ENT_QUOTES) . "</h2>";
include 'nav.php';


# READ SCORE LOGS

# REVISED on 3-25-23 because it kept coming up empty

# read logfile into $csv
$csvData = file_get_contents($logfile);
$lines = explode(PHP_EOL, $csvData);
$array = array();
foreach ($lines as $line) {
    $array[] = str_getcsv($line);
}

# append xfile into $csv (fields: nick,chal,pts[,comment] — use index 0/1/2)
$xlines = explode(PHP_EOL, file_get_contents($xfile));
$name_and_chals = array();
$scores_x = array();
foreach ($xlines as $line) {
	if (strlen(trim($line)) > 2) {
		$xrow = str_getcsv($line);
		if (!isset($xrow[0], $xrow[1], $xrow[2])) continue;
		$key = $xrow[0] . ',' . $xrow[1];
		$pts = intval($xrow[2]);
		$name_and_chals[] = $key;
		$scores_x[] = $pts;
	}
}

# remove duplicates from xfile
$name_and_chals_clean = array();
$scores_clean = array();
for( $i=0; $i<count($name_and_chals); $i++ ) {
	$dupe_loc = -1;
	for( $j=0; $j<count($name_and_chals_clean); $j++ ) {
		if ($name_and_chals[$i] == $name_and_chals_clean[$j]) {
			$dupe_loc = $j;
			if ($scores_x[$i] > $scores_clean[$j]) {
				$scores_clean[$j] = $scores_x[$i];
			}
		}
	}
	if ($dupe_loc < 0) {
		$name_and_chals_clean[] = $name_and_chals[$i];
		$scores_clean[] = $scores_x[$i];
	}
}

# Add clean xfile data to array
for( $j=0; $j<count($name_and_chals_clean); $j++ ) {
	$line = $name_and_chals_clean[$j] . "," . strval($scores_clean[$j]);
	if ($verbose > 1) {
		print("Adding Xline: $line<br>");
	}
    $array[] = str_getcsv($line);
}

# ── Quiz totals (best attempt per quiz per student) ───────────────────────────
$quiz_totals_sb = [];
if (file_exists($results_csv)) {
    $fh = fopen($results_csv, 'r');
    $quiz_best_sb = [];
    while ($fh !== false && ($row = fgetcsv($fh)) !== false) {
        if (count($row) < 3) continue;
        $qnick = trim($row[0]); $qquiz = trim($row[1]); $qscore = intval($row[2]);
        if ($qnick === '') continue;
        if (!isset($quiz_best_sb[$qnick][$qquiz]) || $qscore > $quiz_best_sb[$qnick][$qquiz])
            $quiz_best_sb[$qnick][$qquiz] = $qscore;
    }
    if ($fh !== false) fclose($fh);
    foreach ($quiz_best_sb as $qn => $quizzes) $quiz_totals_sb[$qn] = array_sum($quizzes);
}

# ── Discussion totals ─────────────────────────────────────────────────────────
$discussion_totals_sb = [];
if (!empty($discussions_enabled) && file_exists($discussions_csv)) {
    $fh = fopen($discussions_csv, 'r');
    if ($fh !== false) {
        while (($row = fgetcsv($fh)) !== false) {
            if (empty(trim($row[0]))) continue;
            $dn = trim($row[0]); $dsum = 0;
            for ($d = 1; $d <= 12; $d++) $dsum += intval($row[$d] ?? 0);
            $discussion_totals_sb[$dn] = $dsum;
        }
        fclose($fh);
    }
}

$csv = $array;

# old broken array_map code
#$csv = array_map('str_getcsv', file($logfile));
#$csvx = array_map('str_getcsv', file($xfile));
#$csv = array_merge($csv, $csvx);

$numlines = count($csv);

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

if ($numwinners > 0) {
  print "<table style='border: $border_width solid $border_color; ";
  print "border-radius: 15px; ' ";
  print "cellpadding=0 cellspacing=0 border=0 align='center'> ";
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
    		if ($verbose>1) { print "<p>Solved challenge: $cclean  <p>"; }
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


  $total_score = $scores[$i] + ($quiz_totals_sb[$winners[$i]] ?? 0) + ($discussion_totals_sb[$winners[$i]] ?? 0);
  $sort = (string) ($total_score + 10000);
  $n = "<td align='center'><b><big>&nbsp;" . $winners[$i] . "&nbsp;</big></b></td>";
  $s = "<td align='center'><b>&nbsp;&nbsp;&nbsp;" . (string) $total_score . "&nbsp;&nbsp;&nbsp;</b></td>";
  if ($summary ==0) {
  	$c =  "<td><table cellspacing=5 border=0><tr>$chal_list</tr></table></td>";
  } else {
  	$c =  "";
  }
  
  array_push($outlines, ($sort . $n . $s . $c));
}

rsort($outlines);


# DIAGNOSTIC OUTPUT
if ($verbose > 2) {
  print "<h2>Outlines</h2>";
  for( $i = 0; $i<$numwinners; $i++ ) { 
  	  $cdiag = str_replace("<", "!", $outlines[$i]);
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
	print "<tr><td align='center'>&nbsp;&nbsp;" . (string)($i+1) . "</td>";
  	print substr($outlines[$i], 5);
  

}



print "</td></tr></table>";
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
# v. 2.11 implements xfile for extra credit 7-5-23

?>