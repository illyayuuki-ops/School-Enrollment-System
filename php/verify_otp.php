<?php
declare(strict_types=1);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/includes/otp.php';

$pdo = db_connect();

session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check for pending login
$pending = $_SESSION['pending_login'] ?? null;
if (!$pending || empty($pending['email']) || empty($pending['role'])) {
    header('Location: /login/student.php');
    exit;
}

// Check if pending login has expired
if (isset($pending['expires_at']) && new DateTime() > new DateTime($pending['expires_at'])) {
    unset($_SESSION['pending_login']);
    $_SESSION['login_error'] = 'Login verification code has expired. Please try logging in again.';
    header('Location: /login/' . $pending['role'] . '.php');
    exit;
}

$email = $pending['email'];
$role = $pending['role'];
$fullName = $pending['full_name'] ?? '';
$roleLabels = [
    'student' => 'Student',
    'admin' => 'Administrator',
    'teacher' => 'Faculty',
    'registrar' => 'Registrar',
    'admission' => 'Admissions',
];

$error = null;
$resendSuccess = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = (string) ($_POST['csrf_token'] ?? '');
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $error = 'Invalid or expired form token. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'resend') {
            // Resend OTP
            $code = generate_and_store_otp($pdo, (int) $pending['user_id'], $email, 'login');
            $sent = send_otp_email($email, $fullName, $code, 'login');
            if ($sent) {
                // Update expiry in session
                $_SESSION['pending_login']['expires_at'] = (new DateTime('+5 minutes'))->format('Y-m-d H:i:s');
                $resendSuccess = 'A new verification code has been sent to your email.';
            } else {
                $error = 'Failed to send verification code. Please try again later.';
            }
        } else {
            // Verify OTP
            $code = trim((string) ($_POST['code'] ?? ''));
            
            if (!$code) {
                $error = 'Please enter the verification code.';
            } elseif (verify_and_consume_otp($pdo, $email, 'login', $code)) {
                // OTP verified - complete login
                $stmt = $pdo->prepare("SELECT id, email, full_name, password_hash, role, status FROM users WHERE email = :email AND role = :role LIMIT 1");
                $stmt->execute(['email' => $email, 'role' => $role]);
                $user = $stmt->fetch();
                
                if ($user && $user['status'] === 'active') {
                    session_regenerate_id(true);
                    unset($user['password_hash']);
                    $_SESSION['user'] = $user;
                    $_SESSION['last_activity'] = time();
                    unset($_SESSION['login_error'], $_SESSION['login_role'], $_SESSION['csrf_token'], $_SESSION['pending_login']);
                    
                    // Log successful login
                    try {
                        $logPdo = db_connect();
                        $stmt = $logPdo->prepare("INSERT INTO system_logs (actor_user_id, action, detail, created_at) VALUES (:actor, 'login', :detail, NOW())");
                        $detail = "User {$user['email']} ({$user['role']}) logged in";
                        $stmt->execute(['actor' => $user['id'], 'detail' => $detail]);
                    } catch (PDOException $e) {
                        // Ignore logging errors
                    }
                    
                    header('Location: ' . home_for_role($role));
                    exit;
                } else {
                    $error = 'Account is no longer active. Please contact support.';
                }
            } else {
                $error = 'Invalid or expired verification code. Please try again.';
            }
        }
    }
}

$pageTitle = 'Verify Login';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verify Login · Steiner Maris Academy</title>
    <link rel="icon" type="image/png" href="/assets/logo.png">
    <link rel="stylesheet" href="/assets/app.css">
    <link rel="stylesheet" href="/assets/login.css">
</head>
<body class="login-page">
    <div class="portal-frame">
        <aside class="portal-sidebar">
            <a class="portal-brand" href="/login/<?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?>.php" aria-label="Steiner Maris Academy SMA Portal">
                <img src="/assets/logo.png" alt="Steiner Maris Academy" class="brand-logo" style="height:40px;width:auto">
                <span class="portal-brand-copy">
                    <strong>Steiner Maris Academy</strong>
                    <small>SMA Portal</small>
                </span>
            </a>

            <div class="portal-welcome">
                <span class="eyebrow">Login Verification</span>
                <h1>Verify Your Identity</h1>
                <p>A verification code has been sent to <strong><?= htmlspecialchars($email) ?></strong>.</p>
            </div>

            <div class="portal-sidebar-footer">
                <p>© 2026 Steiner Maris Academy</p>
                <small>Authorized users only</small>
            </div>
        </aside>

        <main class="portal-main">
            <header class="portal-topbar">
                <div class="portal-topbar-welcome">
                    <span class="topbar-menu" aria-hidden="true">☰</span>
                    <span class="eyebrow">SMA Portal</span>
                    <strong>Login Verification</strong>
                </div>
            </header>

            <section class="portal-content">
                <div class="portal-content-inner">
                    <div class="portal-intro">
                        <span class="eyebrow">Steiner Maris Academy</span>
                        <h2>Enter Verification Code</h2>
                        <p>We've sent a 6-digit verification code to <strong><?= htmlspecialchars($email) ?></strong>. Enter it below to complete your login.</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-error" role="alert"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <?php if ($resendSuccess): ?>
                        <div class="alert alert-success" role="alert"><?= htmlspecialchars($resendSuccess) ?></div>
                    <?php endif; ?>

                    <form method="post" action="/verify_otp.php" novalidate id="verify-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="verify">

                        <div class="form-field">
                            <label for="code">Verification Code *</label>
                            <input type="text" id="code" name="code" autocomplete="one-time-code" required 
                                   maxlength="6" pattern="\d{6}" 
                                   placeholder="Enter 6-digit code" 
                                   style="text-align: center; letter-spacing: 8px; font-size: 18px; font-weight: 700; font-family: monospace;">
                            <small class="muted">Enter the 6-digit code from your email</small>
                        </div>

                        <div class="form-actions">
                            <button class="button button-primary" type="submit" name="action" value="verify">Verify & Login <span aria-hidden="true">→</span></button>
                        </div>

                        <p class="auth-help" style="margin-top: 16px;">
                            Didn't receive the code? 
                            <button type="submit" name="action" value="resend" class="text-button">Resend Code</button>
                        </p>
                        
                        <p class="auth-help" style="margin-top: 8px;">
                            <a href="/login/<?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?>.php" style="color: var(--green);">← Back to Login</a>
                        </p>
                    </form>
                </div>
            </section>

            <footer class="portal-footer">
                <span>© 2026 Steiner Maris Academy</span>
                <span>All rights reserved</span>
            </footer>
        </main>
    </div>

    <script>
    (() => {
        const codeInput = document.getElementById('code');
        
        // Auto-format code input
        codeInput.addEventListener('input', (e) => {
            e.target.value = e.target.value.replace(/\D/g, '').slice(0, 6);
        });
        
        // Auto-submit on paste
        codeInput.addEventListener('paste', (e) => {
            const paste = (e.clipboardData || window.clipboardData).getData('text');
            const digits = paste.replace(/\D/g, '').slice(0, 6);
            e.target.value = digits;
            e.preventDefault();
        });
        
        // Auto-focus on load
        codeInput.focus();
    })();
    </script>
</body>
</html>