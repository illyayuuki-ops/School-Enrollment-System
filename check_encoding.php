<?php
$content = file_get_contents('C:/Users/User/Documents/DONT DELETE PROJECTS/School-Enrollment-System/php/includes/sidebar.php');
// Check for BOM
if (substr($content, 0, 3) === "\xef\xbb\xbf") {
    echo "BOM found at start\n";
    $content = substr($content, 3);
}
// Check for CRLF vs LF
$crlf = substr_count($content, "\r\n");
$lf = substr_count($content, "\n");
echo "CRLF: $crlf, LF-only: " . ($lf - $crlf) . "\n";
// Try to parse
$result = null;
$error = null;
set_error_handler(function($errno, $errstr) use (&$error) {
    $error = $errstr;
    return true;
});
// Check for non-printable characters
for ($i = 0; $i < strlen($content); $i++) {
    $ord = ord($content[$i]);
    if ($ord < 32 && $ord !== 10 && $ord !== 13 && $ord !== 9) {
        echo "Non-printable char at position $i: $ord\n";
    }
}
echo "Content length: " . strlen($content) . "\n";
