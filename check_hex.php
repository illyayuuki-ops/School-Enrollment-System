<?php
$content = file_get_contents('C:/Users/User/Documents/DONT DELETE PROJECTS/School-Enrollment-System/php/includes/sidebar.php');
$lines = explode("\n", $content);
// Show hex of lines 54-57 (0-indexed: 53-56)
for ($i = 53; $i <= 56; $i++) {
    if (isset($lines[$i])) {
        echo "Line " . ($i+1) . ":\n";
        echo "  Hex: " . bin2hex($lines[$i]) . "\n";
        echo "  Len: " . strlen($lines[$i]) . "\n";
    }
}
