<?php
$content = file_get_contents('C:/Users/User/Documents/DONT DELETE PROJECTS/School-Enrollment-System/php/includes/sidebar.php');
$lines = explode("\n", $content);
// Build progressively shorter files and try to lint them
for ($count = 56; $count >= 1; $count--) {
    $test = implode("\n", array_slice($lines, 0, $count)) . "\n";
    $tmpFile = tempnam(sys_get_temp_dir(), 'php_');
    file_put_contents($tmpFile, $test);
    $output = [];
    $ret = 0;
    exec('php -l ' . escapeshellarg($tmpFile) . ' 2>&1', $output, $ret);
    unlink($tmpFile);
    if ($ret === 0) {
        echo "SUCCESS with $count lines\n";
        echo implode("\n", array_slice($lines, 0, $count));
        break;
    }
}
