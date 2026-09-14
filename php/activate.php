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

$error = null;
$success = null;
$showForm = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = (string) ($_POST['csrf_token'] ?? '');
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $error = 'Invalid or expired form token. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        $email = trim((string) ($_POST['email'] ?? ''));
        
        if ($action === 'resend') {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Invalid email format.';
            } else {
                // Get user info
                $stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE email = :email");
                $stmt->execute(['email' => $email]);
                $user = $stmt->fetch();
                
                if ($user) {
                    $result = resend_otp($pdo, $email, $user['full_name'], 'activation');
                    if ($result['success']) {
                        $success = $result['message'];
                    } else {
                        $error = $result['message'];
                    }
                } else {
                    // Don't reveal if email exists
                    $success = 'If the email exists, a new activation code has been sent.';
                }
            }
        } else {
            // Verify activation code
            $code = trim((string) ($_POST['code'] ?? ''));
            $newPassword = (string) ($_POST['new_password'] ?? '');
            $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
            
            if (!$email || !$code || !$newPassword || !$confirmPassword) {
                $error = 'All fields are required.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Invalid email format.';
            } elseif (strlen($newPassword) < 8) {
                $error = 'Password must be at least 8 characters.';
            } elseif ($newPassword !== $confirmPassword) {
                $error = 'Passwords do not match.';
            } else {
                // Verify OTP
                if (verify_and_consume_otp($pdo, $email, 'activation', $code)) {
                    // Activate account and set new password
                    $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    try {
                        $stmt = $pdo->prepare("
                            UPDATE users 
                            SET activated = TRUE, status = 'active', password_hash = :phash 
                            WHERE email = :email AND activated = FALSE
                        ");
                        $stmt->execute(['phash' => password_hash($newPassword, PASSWORD_DEFAULT), 'email' => $email]);
                        
                        if ($stmt->rowCount() > 0) {
                            // Log the activation
                            try {
                                $stmt = $pdo->prepare("INSERT INTO system_logs (actor_user_id, action, detail, created_at) VALUES (:actor, 'account_activated', :detail, NOW())");
                                $stmt->execute(['actor' => 0, 'detail' => "User $email activated their account"]);
                            } catch (PDOException $e) {
                                // Ignore logging errors
                            }
                            
                            $success = 'Your account has been activated successfully. You can now log in with your new password.';
                            $showForm = false;
                        } else {
                            $error = 'This account is already activated or does not exist.';
                        }
                    } catch (PDOException $e) {
                        $error = 'A database error occurred. Please try again.';
                    }
                } else {
                    $error = 'Invalid or expired activation code. Please try again or request a new code.';
                }
            }
        }
    }
}

$pageTitle = 'Activate Account';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Activate Account · Steiner Maris Academy</title>
    <link rel="icon" type="image/png" href="/assets/logo.png">
    <link rel="stylesheet" href="/assets/app.css">
    <link rel="stylesheet" href="/assets/login.css">
</head>
<body class="login-page">
    <div class="portal-frame">
        <aside class="portal-sidebar">
            <a class="portal-brand" href="/login/student.php" aria-label="Steiner Maris Academy SMA Portal">
                <img src="/assets/logo.png" alt="Steiner Maris Academy" class="brand-logo" style="height:40px;width:auto">
                <span class="portal-brand-copy">
                    <strong>Steiner Maris Academy</strong>
                    <small>SMA Portal</small>
                </span>
            </a>

            <div class="portal-welcome">
                <span class="eyebrow">Account Activation</span>
                <h1>Activate Your Account</h1>
                <p>Enter the activation code sent to your email to activate your student account.</p>
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
                    <strong>Account Activation</strong>
                </div>
            </header>

            <section class="portal-content">
                <div class="portal-content-inner">
                    <div class="portal-intro">
                        <span class="eyebrow">Steiner Maris Academy</span>
                        <h2>Activate Your Account</h2>
                        <p>Enter the 6-digit activation code sent to your email to activate your student account and set your password.</p>
                    </div>

                    <?php if ($success): ?>
                        <div class="alert alert-success" role="alert"><?= htmlspecialchars($success) ?></div>
                        <?php if (!$showForm): ?>
                            <p style="margin-top: 16px; text-align: center;">
                                <a href="/login/student.php" class="button button-primary">Proceed to Login</a>
                            </p>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-error" role="alert"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <?php if ($showForm): ?>
                        <form method="post" action="/activate.php" novalidate id="activate-form">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="action" value="verify">

                            <div class="form-field">
                                <label for="email">Email *</label>
                                <input type="email" id="email" name="email" autocomplete="email" required 
                                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                                       placeholder="Enter your registered email">
                            </div>

                            <div class="form-field">
                                <label for="code">Activation Code *</label>
                                <input type="text" id="code" name="code" autocomplete="one-time-code" required 
                                       maxlength="6" pattern="\d{6}" 
                                       placeholder="Enter 6-digit code" 
                                       style="text-align: center; letter-spacing: 8px; font-size: 18px; font-weight: 700; font-family: monospace;">
                                <small class="muted">Enter the 6-digit code from your email</small>
                            </div>

                            <div class="form-field">
                                <label for="new_password">New Password *</label>
                                <input type="password" id="new_password" name="new_password" autocomplete="new-password" required 
                                       minlength="8" placeholder="Enter new password (min 8 characters)">
                            </div>

                            <div class="form-field">
                                <label for="confirm_password">Confirm Password *</label>
                                <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" required 
                                       placeholder="Confirm your new password">
                            </div>

                            <div class="form-actions">
                                <button class="button button-primary" type="submit" name="action" value="verify">Activate Account <span aria-hidden="true">→</span></button>
                            </div>

                            <p class="auth-help" style="margin-top: 16px;">
                                Didn't receive the code? 
                                <button type="submit" name="action" value="resend" class="text-button">Resend Code</button>
                            </p>
                        </form>
                    <?php endif; ?>
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
        const form = document.getElementById('activate-form');
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
            if (digits.length === 6) {
                // Auto-focus next field or submit
                const nextInput = document.getElementById('new_password');
                if (nextInput) nextInput.focus();
            }
        });
    })();
    </script>
</body>
</html>