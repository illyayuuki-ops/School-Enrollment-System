<?php
$content = file_get_contents('C:/Users/User/Documents/DONT DELETE PROJECTS/School-Enrollment-System/php/includes/sidebar.php');
$lines = explode("\n", $content);
// Check all lines for non-ASCII or weird characters
for ($i = 0; $i < count($lines); $i++) {
    for ($j = 0; $j < strlen($lines[$i]); $j++) {
        $ord = ord($lines[$i][$j]);
        if ($ord > 127) {
            echo "Non-ASCII at line " . ($i+1) . " pos $j: $ord\n";
        }
    }
}
// Also check line 16 opening
echo "Line 16 hex: " . bin2hex($lines[15]) . "\n";
echo "Line 55 hex: " . bin2hex($lines[54]) . "\n";
echo "Line 56 hex: " . bin2hex($lines[55]) . "\n";
