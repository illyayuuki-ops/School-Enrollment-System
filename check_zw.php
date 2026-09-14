<?php
$content = file_get_contents('C:/Users/User/Documents/DONT DELETE PROJECTS/School-Enrollment-System/php/includes/sidebar.php');
// Check for zero-width characters and other Unicode control chars
$zeroWidth = ["\x{200b}", "\x{200c}", "\x{200d}", "\x{200e}", "\x{200f}", "\x{2060}", "\x{feff}"];
foreach ($zeroWidth as $zw) {
    if (mb_strpos($content, $zw) !== false) {
        echo "Found zero-width: " . bin2hex($zw) . "\n";
    }
}
// Try replacing the file content and see if it lints
$fixed = str_replace("\x{200b}", "", $content);
$fixed = str_replace("\x{200c}", "", $fixed);
$fixed = str_replace("\x{200d}", "", $fixed);
$tmpFile = tempnam(sys_get_temp_dir(), 'php_');
file_put_contents($tmpFile, $fixed);
$output = [];
$ret = 0;
exec('php -l ' . escapeshellarg($tmpFile) . ' 2>&1', $output, $ret);
echo "After removing zero-width: " . implode('; ', $output) . "\n";
unlink($tmpFile);
// Also try with just line 56 replaced
$lines = explode("\n", $content);
$lines[55] = "];\n\$navigation = \$navigation[\$role] ?? [['label' => 'Log in', 'href' => '/login/student.php']];";
$test = implode("\n", $lines);
$tmpFile = tempnam(sys_get_temp_dir(), 'php_');
file_put_contents($tmpFile, $test);
$output = [];
$ret = 0;
exec('php -l ' . escapeshellarg($tmpFile) . ' 2>&1', $output, $ret);
echo "With replaced line 56: " . implode('; ', $output) . "\n";
unlink($tmpFile);
