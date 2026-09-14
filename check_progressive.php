<?php
$content = file_get_contents('C:/Users/User/Documents/DONT DELETE PROJECTS/School-Enrollment-System/php/includes/sidebar.php');
$lines = explode("\n", $content);
// Try linting progressively larger chunks
for ($end = 56; $end <= 60; $end++) {
    $chunk = implode("\n", array_slice($lines, 0, $end)) . "\n";
    $tmpFile = tempnam(sys_get_temp_dir(), 'php_test_');
    file_put_contents($tmpFile, $chunk);
    ob_start();
    $output = [];
    $ret = 0;
    exec('php -l ' . escapeshellarg($tmpFile), $output, $ret);
    unlink($tmpFile);
    echo "Lines 1-$end: " . implode('; ', $output) . "\n";
}
