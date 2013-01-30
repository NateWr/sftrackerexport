<?php

/*
 * Combine all the exported csv files into a single file for easier importing.
 * -----
 * This script will read all the exported csv files in your folder and
 * combine them into larger files for easier importing. I found that more
 * than 500 items in a single CSV file caused slow-downs when importing,
 * so I recommend you don't go higher than that.
 *
 * Each $i is a new csv file, so you will want to set the number of files to
 * combine into one by changing the integer in the line:
 *
 * if (is_int($i / 5) || $i == 0) {
 * 
 * Setting this to 5 means that 5 CSV files will be combined.
 */

set_time_limit(600);
echo '<pre>';

$types = array("bugs", "feature-requests", "patches");
foreach ($types as $type) {
	$continue = true;
	for ($i=0; $continue === true; $i++) {
		$fname = 'export/' . $type . '_page-' . $i . '.csv';
		if (file_exists($fname)) {
			$source = file_get_contents($fname);
			
			if (is_int($i / 5) || $i == 0) {
				$batch = $i;
			} else {
				// Strip the header information from all but the first file in the compiled batch.
				$lines = explode("\n", $source);
				array_shift($lines);
				$source = join("\n", $lines);
			}
				
			$target = 'export/' . $type . '_compiled_' . $batch . '.csv';
			if (file_exists($target)) {
				if (file_put_contents($target, $source, FILE_APPEND) === false) {
					echo "\nFAILED APPEND: " . $fname;
				} else {
					echo "\nSUCCESS APPEND: " . $fname;
				}
			} else {
				if (file_put_contents($target, $source) === false) {
					echo "\nFAILED CREATE: " . $fname;
					echo "\nFAILED CREATE FILE: " . $fname;
				} else {
					echo "\nSUCCESS CREATE: " . $fname;
				}
			}
		} else {
			$continue = false;
		}
		echo "\n" . $i;
		flush();
		ob_flush();
	}
}
?>