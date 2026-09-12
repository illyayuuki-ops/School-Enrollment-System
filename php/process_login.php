<?php
declare(strict_types=1);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/auth.php';

$allowedRoles = ['admin', 'admission', 'registrar', 'teacher', 'student'];
$role = (string) ($_POST['user_role'] ?? '');
$backUrl = in_array($role, $allowedRoles, true) ? "/login/$role.php" : '/login/student.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $backUrl);
    exit;
}

$csrfToken = (string) ($_POST['csrf_token'] ?? '');
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    $_SESSION['login_error'] = 'Your login form expired. Please try again.';
    header('Location: ' . $backUrl);
    exit;
}

$email = trim((string) ($_POST['email'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$roleLabels = [
    'student' => 'Student',
    'admin' => 'Administrator',
    'teacher' => 'Faculty',
    'registrar' => 'Registrar',
    'admission' => 'Admissions',
];

$_SESSION['login_role'] = $role;

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '' || !in_array($role, $allowedRoles, true)) {
    $_SESSION['login_error'] = 'Enter a valid email, password, and workspace role.';
    header('Location: ' . $backUrl);
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
    $_SESSION['login_error'] = "This account is not a {$roleLabels[$role]} account.";
    header('Location: ' . $backUrl);
    exit;
}

session_regenerate_id(true);
unset($user['password_hash']);
$_SESSION['user'] = $user;
$_SESSION['last_activity'] = time();
unset($_SESSION['login_error'], $_SESSION['login_role'], $_SESSION['csrf_token']);

// Log successful login
try {
    $logPdo = db_connect();
    $stmt = $logPdo->prepare("INSERT INTO system_logs (actor_user_id, action, detail, created_at) VALUES (:actor, 'login', :detail, NOW())");
    $detail = "User {$user['email']} ({$user['role']}) logged in";
    $stmt->execute(['actor' => $user['id'], 'detail' => $detail]);
} catch (PDOException $e) {
    // Ignore logging errors - never break the main flow
}

header('Location: ' . home_for_role($role));
exit;
