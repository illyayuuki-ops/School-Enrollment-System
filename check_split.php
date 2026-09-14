<?php
$content = file_get_contents('C:/Users/User/Documents/DONT DELETE PROJECTS/School-Enrollment-System/php/includes/sidebar.php');
$lines = explode("\n", $content);
// Replace line 56 with a working syntax
$lines[55] = "];";
// Add new lines after line 56
$newLines = $lines;
$newLines[55] = "];";
// Insert after line 56
array_splice($newLines, 56, 0, "\$navigation = \$navigation[\$role] ?? [['label' => 'Log in', 'href' => '/login/student.php']];");
$test = implode("\n", $newLines);
$tmpFile = tempnam(sys_get_temp_dir(), 'php_');
file_put_contents($tmpFile, $test);
$output = [];
$ret = 0;
exec('php -l ' . escapeshellarg($tmpFile) . ' 2>&1', $output, $ret);
echo "With split syntax: " . implode('; ', $output) . "\n";
unlink($tmpFile);
// Also show the changed area
for ($i = 53; $i <= 58 && $i < count($newLines); $i++) {
    echo ($i+1) . ": " . $newLines[$i] . "\n";
}
