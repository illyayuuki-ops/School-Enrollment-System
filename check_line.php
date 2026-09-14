<?php
$lines = file('C:/Users/User/Documents/DONT DELETE PROJECTS/School-Enrollment-System/php/includes/sidebar.php');
echo 'Total lines: ' . count($lines) . PHP_EOL;
echo 'Line 56 hex: ' . bin2hex($lines[55]) . PHP_EOL;
echo 'Line 56 raw: ' . $lines[55] . PHP_EOL;
echo 'Line 56 visible: ' . preg_replace('/[^[:print:][:space:]]/', '?', $lines[55]) . PHP_EOL;
