<?php

/*
 * Small utility to locate and display specific rows.
 * -----
 * This is useful if bug genie identifies a problem with a row,
 * you can use this to look at that row and rows around it to
 * discern the problem. Change the $row > and < numbers to
 * determine which rows get printed to the screen.
 */
 
$row = 1;
if (($handle = fopen("export/patches_compiled_0.csv", "r")) !== FALSE) {
    while (($data = fgetcsv($handle, 0, ",")) !== FALSE) {
        $num = count($data);
        echo "<p> $num fields in line $row: <br /></p>\n";
        $row++;
        for ($c=0; $c < $num; $c++) {
			if ($row > 50 && $row < 55) {
				echo $data[$c] . "<br />\n";
			}
        }
    }
    fclose($handle);
}

?>