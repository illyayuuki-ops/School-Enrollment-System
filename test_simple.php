<?php
// Test 1: Simple case
$a = ['x' => [], 'y' => []];
$b = $a['x'] ?? [['label' => 'test']];
echo "Test 1 OK\n";

// Test 2: With comma before closing
$c = [
    'admin' => [1, 2],
    'student' => [3, 4],
];
$d = $c['student'] ?? [['label' => 'test']];
echo "Test 2 OK\n";

// Test 3: Direct reassignment
$nav = [
    'admin' => [1],
];
$nav = $nav['admin'] ?? [['label' => 'test']];
echo "Test 3 OK\n";
