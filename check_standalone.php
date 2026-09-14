<?php
$lines = explode("\n", file_get_contents('C:/Users/User/Documents/DONT DELETE PROJECTS/School-Enrollment-System/php/includes/sidebar.php'));
// Create a minimal version with just the array structure
$testLines = array_slice($lines, 15, 41); // lines 16-56 (0-indexed 15-55)
$test = "<?php\n" . implode("\n", $testLines) . "\n";
$tmpFile = tempnam(sys_get_temp_dir(), 'php_');
file_put_contents($tmpFile, $test);
$output = [];
$ret = 0;
exec('php -l ' . escapeshellarg($tmpFile) . ' 2>&1', $output, $ret);
echo "Lines 16-56 standalone:\n" . implode('; ', $output) . "\n\n";
// Also print the test content
echo "Test content:\n" . $test . "\n";
unlink($tmpFile);
