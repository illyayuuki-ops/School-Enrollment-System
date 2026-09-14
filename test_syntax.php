<?php
$navigation = [
    'admin' => [],
    'student' => [],
];
$role = 'admin';
$result = $navigation[$role] ?? [['label' => 'Log in', 'href' => '/login/student.php']];
echo $result[0]['label'] . PHP_EOL;
