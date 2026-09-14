<?php
// Test with trailing comma before main array close
$navigation = [
    'admin' => [1],
    'student' => [2],
    ];
$role = 'admin';
$navigation = $navigation[$role] ?? [['label' => 'Log in', 'href' => '/login/student.php']];
echo "Test 1 OK\n";

// Test exactly matching sidebar.php structure
$nav2 = [
    'admin' => [
        ['label' => 'Dashboard', 'href' => '/admin/home.php'],
    ],
    'student' => [
        ['label' => 'Dashboard', 'href' => '/student/home.php'],
    ],
];
$role2 = 'admin';
$nav2 = $nav2[$role2] ?? [['label' => 'Log in', 'href' => '/login/student.php']];
echo "Test 2 OK\n";
