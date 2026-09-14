<?php
$navigation = [
    'admin' => [
        ['label' => 'Dashboard', 'href' => '/admin/home.php'],
    ],
    'student' => [
        ['label' => 'Dashboard', 'href' => '/student/home.php'],
    ],
];
$role = 'admin';
$result = $navigation[$role] ?? [['label' => 'Log in', 'href' => '/login/student.php']];
echo $result[0]['label'] . PHP_EOL;
