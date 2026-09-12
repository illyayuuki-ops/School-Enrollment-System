<?php
declare(strict_types=1);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/includes/otp.php';

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
    'SELECT id, email, full_name, password_hash, role, status, activated
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

// Check if account is activated
if (!$user['activated']) {
    $_SESSION['login_error'] = 'This account has not been activated yet. Please check your email for the activation code and complete activation at <a href="/activate.php">/activate.php</a>.';
    header('Location: ' . $backUrl);
    exit;
}

// Generate login OTP and store pending login in session
$loginCode = generate_and_store_otp(db_connect(), (int) $user['id'], $email, 'login');
$emailSent = send_otp_email($email, $user['full_name'], $loginCode, 'login');

if (!$emailSent) {
    $_SESSION['login_error'] = 'Failed to send login verification code. Please try again later.';
    header('Location: ' . $backUrl);
    exit;
}

// Store pending login info in session
$_SESSION['pending_login'] = [
    'user_id' => $user['id'],
    'email' => $email,
    'role' => $role,
    'full_name' => $user['full_name'],
    'expires_at' => (new DateTime('+5 minutes'))->format('Y-m-d H:i:s'),
];

// Redirect to OTP verification page
header('Location: /verify_otp.php');
exit;