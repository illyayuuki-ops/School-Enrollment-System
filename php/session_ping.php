<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

header('Content-Type: application/json');

if (empty($_SESSION['user'])) {
    echo json_encode(['ok' => false, 'redirect' => '/login/student.php']);
    exit;
}

$now = time();
if (isset($_SESSION['last_activity']) && ($now - $_SESSION['last_activity']) > SESSION_IDLE_LIMIT) {
    $role = $_SESSION['user']['role'] ?? null;
    $target = login_for_role($role);
    $_SESSION = [];
    session_destroy();
    echo json_encode(['ok' => false, 'redirect' => $target . '?expired=1']);
    exit;
}

$_SESSION['last_activity'] = $now;
echo json_encode(['ok' => true, 'remaining' => SESSION_IDLE_LIMIT]);