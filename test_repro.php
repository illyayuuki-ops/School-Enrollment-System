<?php
$navigation = [
    'admin' => [
        'x' => 1,
    ],
    'student' => [
        'y' => 2,
    ],
];
$role = 'admin';
$result = $navigation[$role] ?? [['label' => 'Log in']];
echo "OK\n";
