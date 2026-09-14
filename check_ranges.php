<?php
$lines = explode("\n", file_get_contents('C:/Users/User/Documents/DONT DELETE PROJECTS/School-Enrollment-System/php/includes/sidebar.php'));
// Test just first 16 lines
$test = implode("\n", array_slice($lines, 0, 16)) . "\n";
$tmpFile = tempnam(sys_get_temp_dir(), 'php_');
file_put_contents($tmpFile, $test);
$output = [];
$ret = 0;
exec('php -l ' . escapeshellarg($tmpFile) . ' 2>&1', $output, $ret);
echo "Lines 1-16: " . implode('; ', $output) . "\n";
unlink($tmpFile);

// Test first 15 lines (which was good)
$test = implode("\n", array_slice($lines, 0, 15)) . "\n";
$tmpFile = tempnam(sys_get_temp_dir(), 'php_');
file_put_contents($tmpFile, $test);
$output = [];
$ret = 0;
exec('php -l ' . escapeshellarg($tmpFile) . ' 2>&1', $output, $ret);
echo "Lines 1-15: " . implode('; ', $output) . "\n";
unlink($tmpFile);

// Test first 17 lines
$test = implode("\n", array_slice($lines, 0, 17)) . "\n";
$tmpFile = tempnam(sys_get_temp_dir(), 'php_');
file_put_contents($tmpFile, $test);
$output = [];
$ret = 0;
exec('php -l ' . escapeshellarg($tmpFile) . ' 2>&1', $output, $ret);
echo "Lines 1-17: " . implode('; ', $output) . "\n";
unlink($tmpFile);
