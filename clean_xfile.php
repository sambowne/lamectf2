<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

# Cleans xfile, keeping only the max of Google Badges
# v1.0 3-22-26
# v 1.1 5-3-26 with verbose fixed

# IMPORT ANSWERS FILE
include 'config.php';

if (! isset($xfile) ) {
	print" Error: xfile not set in config.php";
	exit;
}

$xfile_dedup = $xfile . "_dedup";

if (isset($verbose) ) {
	$verbose = $_GET['verbose'];
} else { $verbose = 0; }

print("Verbose: $verbose <br>");


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
if (! file_put_contents($xfile_dedup, '')) 
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












?>