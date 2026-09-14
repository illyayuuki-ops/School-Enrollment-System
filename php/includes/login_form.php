<?php
require_once dirname(__DIR__) . '/includes/settings.php';

$roleLabels = [
    'student' => 'Student',
    'admin' => 'Administrator',
    'teacher' => 'Faculty',
    'registrar' => 'Registrar',
    'admission' => 'Admissions',
];
if (!isset($role) || !isset($roleLabels[$role])) { header('Location: /login/student.php'); exit; }

$expired = isset($_GET['expired']) && $_GET['expired'] === '1';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · Steiner Maris Academy</title>
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
                <span class="eyebrow">Welcome</span>
                <h1><?= htmlspecialchars($roleLabels[$role], ENT_QUOTES, 'UTF-8') ?> Portal</h1>
                <p>Secure access to your academy workspace.</p>
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
                    <strong>Welcome to your <?= htmlspecialchars($roleLabels[$role], ENT_QUOTES, 'UTF-8') ?> workspace</strong>
                </div>
<div class="portal-topbar-meta">
                        <span class="secure-chip"><span class="secure-dot" aria-hidden="true"></span>Secure sign-in</span>
                        <span class="topbar-year"><?= htmlspecialchars(academic_year_label($pdo)) ?></span>
                    </div>
            </header>

            <section class="portal-content">
                <div class="portal-content-inner">
                    <div class="portal-intro">
                        <span class="eyebrow">Steiner Maris Academy</span>
                        <h2><?= htmlspecialchars($roleLabels[$role], ENT_QUOTES, 'UTF-8') ?> Portal</h2>
                        <p>Sign in with your school credentials to continue to the <?= htmlspecialchars($roleLabels[$role], ENT_QUOTES, 'UTF-8') ?> workspace.</p>
                        <div class="portal-trust-row">
                            <span><span class="trust-mark" aria-hidden="true">✓</span>Private and secure</span>
                            <span><span class="trust-mark" aria-hidden="true">✓</span><?= htmlspecialchars(academic_year_label($pdo)) ?></span>
                        </div>
                    </div>

                    <section class="auth-card portal-signin-card" aria-labelledby="signin-title">
                        <div class="card-heading">
                            <span class="eyebrow">Secure workspace</span>
                            <h2 id="signin-title"><?= htmlspecialchars($roleLabels[$role], ENT_QUOTES, 'UTF-8') ?> Portal</h2>
                            <p class="muted">Sign in to the SMA portal.</p>
                        </div>

                        <?php if ($expired): ?>
                            <div class="alert alert-error" role="alert">Your session expired due to inactivity. Please sign in again.</div>
                        <?php endif; ?>

                        <?php if ($error): ?>
                            <div class="alert alert-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>

                        <form method="post" action="/process_login.php" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="user_role" value="<?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?>">

                            <label for="email">Email / User ID</label>
                            <div class="field">
                                <input id="email" type="email" name="email" autocomplete="username" placeholder="Enter your school email or User ID" required>
                            </div>

                            <label for="password">Password</label>
                            <div class="field password-field">
                                <input id="password" type="password" name="password" autocomplete="current-password" placeholder="Enter your password" required>
                                <button type="button" class="password-toggle" data-toggle-password aria-label="Show password">Show</button>
                            </div>

                            <div class="form-actions">
                                <button type="button" class="text-button" data-open-forgot>Forgot password?</button>
                                <button class="button button-primary" type="submit">Sign in <span aria-hidden="true">→</span></button>
                            </div>
                        </form>

                        <p class="auth-help">Protected by Steiner Maris Academy access controls.</p>

                        <?php if ($role === 'student'): ?>
                            <p class="auth-help" style="margin-top: 12px;">
                                New student? <a href="/register.php" style="color: var(--green); font-weight: 700;">Register here</a>
                            </p>
                        <?php endif; ?>
                    </section>
                </div>
            </section>

            <footer class="portal-footer">
                <span>© 2026 Steiner Maris Academy</span>
                <span>All rights reserved</span>
            </footer>
        </main>
    </div>

    <div class="modal-backdrop" data-forgot-modal hidden>
        <section class="recovery-modal" role="dialog" aria-modal="true" aria-labelledby="recovery-title">
            <button type="button" class="modal-close" data-close-forgot aria-label="Close">×</button>
            <span class="eyebrow">Credential recovery</span>
            <h2 id="recovery-title">Need help signing in?</h2>
            <p class="muted">Contact the registrar's office with your school email and portal role. They can verify your identity and reset your access.</p>
            <a class="button button-primary" href="mailto:registrar@sma.edu.ph">Email registrar's office</a>
        </section>
    </div>

    <script>
        (() => {
            const password = document.getElementById('password');
            const passwordToggle = document.querySelector('[data-toggle-password]');
            passwordToggle.addEventListener('click', () => {
                const visible = password.type === 'text';
                password.type = visible ? 'password' : 'text';
                passwordToggle.textContent = visible ? 'Show' : 'Hide';
                passwordToggle.setAttribute('aria-label', visible ? 'Show password' : 'Hide password');
            });

            const modal = document.querySelector('[data-forgot-modal]');
            document.querySelector('[data-open-forgot]').addEventListener('click', () => { modal.hidden = false; });
            document.querySelector('[data-close-forgot]').addEventListener('click', () => { modal.hidden = true; });
            modal.addEventListener('click', (event) => { if (event.target === modal) modal.hidden = true; });
        })();
    </script>
</body>
</html>
