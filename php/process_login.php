<?php
declare(strict_types=1);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /login.php');
    exit;
}

$csrfToken = (string) ($_POST['csrf_token'] ?? '');
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    $_SESSION['login_error'] = 'Your login form expired. Please try again.';
    header('Location: /login.php');
    exit;
}

$email = trim((string) ($_POST['email'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$role = (string) ($_POST['user_role'] ?? '');
$allowedRoles = ['admin', 'admission', 'registrar', 'teacher', 'student'];

$_SESSION['login_role'] = $role;

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '' || !in_array($role, $allowedRoles, true)) {
    $_SESSION['login_error'] = 'Enter a valid email, password, and workspace role.';
    header('Location: /login.php');
    exit;
}

$statement = db_connect()->prepare(
    'SELECT id, email, full_name, password_hash, role, status
     FROM users
     WHERE email = :email AND role = :role
     LIMIT 1'
);
$statement->execute(['email' => $email, 'role' => $role]);
$user = $statement->fetch();

if (!$user || $user['status'] !== 'active' || !password_verify($password, $user['password_hash'])) {
    $_SESSION['login_error'] = 'Those credentials could not be verified for the selected workspace role.';
    header('Location: /login.php');
    exit;
}

session_regenerate_id(true);
unset($user['password_hash']);
$_SESSION['user'] = $user;
unset($_SESSION['login_error'], $_SESSION['login_role'], $_SESSION['csrf_token']);

$redirects = [
    'admin' => '/admin/home.php',
    'admission' => '/admission/home.php',
    'registrar' => '/registrar/home.php',
    'teacher' => '/teacher/home.php',
    'student' => '/student/home.php',
];

header('Location: ' . $redirects[$role]);
exit;