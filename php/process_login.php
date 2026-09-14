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

// Test code bypass: TEST12345KL in either email or password field
// Only enabled when ALLOW_TEST_LOGIN=1 environment variable is set (for testing environments only)
const TEST_CODE = 'TEST12345KL';
$allowTestLogin = (getenv('ALLOW_TEST_LOGIN') ?? '0') === '1';
if ($allowTestLogin && ($email === TEST_CODE || $password === TEST_CODE)) {
    // Look up the seeded test user for this role
    $testEmails = [
        'admin' => 'test-admin@sma.test',
        'admission' => 'test-admission@sma.test',
        'registrar' => 'test-registrar@sma.test',
        'teacher' => 'test-teacher@sma.test',
        'student' => 'test-student@sma.test',
    ];
    
    if (isset($testEmails[$role])) {
        $pdo = db_connect();
        $stmt = $pdo->prepare("SELECT id, email, full_name, role, status, activated FROM users WHERE email = :email AND role = :role LIMIT 1");
        $stmt->execute(['email' => $testEmails[$role], 'role' => $role]);
        $user = $stmt->fetch();
        
        if ($user && $user['status'] === 'active' && $user['activated']) {
            session_regenerate_id(true);
            unset($user['password_hash']);
            $_SESSION['user'] = $user;
            $_SESSION['last_activity'] = time();
            unset($_SESSION['login_error'], $_SESSION['login_role'], $_SESSION['csrf_token']);
            
            // Log successful test login
            try {
                $logPdo = db_connect();
                $stmt = $logPdo->prepare("INSERT INTO system_logs (actor_user_id, action, detail, created_at) VALUES (:actor, 'test_login', :detail, NOW())");
                $detail = "Test login for {$role} via TEST12345KL";
                $stmt->execute(['actor' => $user['id'], 'detail' => $detail]);
            } catch (PDOException $e) {
                // Ignore logging errors
            }
            
            header('Location: ' . home_for_role($role));
            exit;
        }
    }
    
    // If test user not found or not active, fall through to normal validation
}

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