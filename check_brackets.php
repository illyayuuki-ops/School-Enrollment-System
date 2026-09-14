<?php
$content = file_get_contents('C:/Users/User/Documents/DONT DELETE PROJECTS/School-Enrollment-System/php/includes/sidebar.php');
$lines = explode("\n", $content);
$open = 0;
$close = 0;
for ($i = 0; $i < 56; $i++) {
    $line = $lines[$i];
    // Strip strings to avoid counting brackets in strings
    $stripped = preg_replace("/'[^']*'/", "''", $line);
    $open += substr_count($stripped, '[');
    $close += substr_count($stripped, ']');
    echo "After line " . ($i+1) . ": open=$open close=$close\n";
}
