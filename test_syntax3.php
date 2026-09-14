<?php
$navigation = [
    'admin' => [],
    'student' => [],
];
$role = 'admin';
$navigation = $navigation[$role] ?? [['label' => 'Log in', 'href' => '/login/student.php']];
