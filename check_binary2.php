<?php
$content = file_get_contents('C:/Users/User/Documents/DONT DELETE PROJECTS/School-Enrollment-System/php/includes/sidebar.php');
$lines = explode("\n", $content);
// Binary search: find the first line that causes an error
$low = 1;
$high = count($lines);
$lastGood = 0;
while ($low <= $high) {
    $mid = (int)(($low + $high) / 2);
    $test = implode("\n", array_slice($lines, 0, $mid)) . "\n";
    $tmpFile = tempnam(sys_get_temp_dir(), 'php_');
    file_put_contents($tmpFile, $test);
    $output = [];
    $ret = 0;
    exec('php -l ' . escapeshellarg($tmpFile) . ' 2>&1', $output, $ret);
    unlink($tmpFile);
    if ($ret === 0) {
        $lastGood = $mid;
        $low = $mid + 1;
    } else {
        $high = $mid - 1;
    }
}
echo "Last good: $lastGood\n";
echo "First bad: " . ($lastGood + 1) . "\n";
echo "That line: " . ($lines[$lastGood] ?? 'N/A') . "\n";
echo "Next line: " . ($lines[$lastGood] ?? 'N/A') . "\n";
