<?php
// Test with exact same structure but use single quotes for student.php path
$navigation = [
    'admin' => [
        ['label' => 'Dashboard', 'href' => '/admin/home.php'],
    ],
    'student' => [
        ['label' => 'Dashboard', 'href' => '/student/home.php'],
    ],
];
$role = 'admin';
$navigation = $navigation[$role] ?? [['label' => 'Log in', 'href' => '/login/student.php']];
echo $navigation[0]['label'] . "\n";
