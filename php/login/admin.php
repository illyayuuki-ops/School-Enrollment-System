<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../db_connect.php';
try {
    $pdo = db_connect();
} catch (Throwable $e) {
    $pdo = null;
}
if (!empty($_SESSION['user'])) {
    header('Location: ' . home_for_role($_SESSION['user']['role'] ?? 'student'));
    exit;
}
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$error = $_SESSION['login_error'] ?? null;
unset($_SESSION['login_error']);
$role = 'admin';
require __DIR__ . '/../includes/login_form.php';
