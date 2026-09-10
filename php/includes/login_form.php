<?php
$roleLabels = [
    'student' => 'Student',
    'admin' => 'Administrator',
    'teacher' => 'Faculty',
    'registrar' => 'Registrar',
    'admission' => 'Admissions',
];
if (!isset($role) || !isset($roleLabels[$role])) { header('Location: /login/student.php'); exit; }
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · Northstar Enrollment</title>
    <link rel="stylesheet" href="/assets/app.css">
    <link rel="stylesheet" href="/assets/login.css">
</head>
<body class="login-page">
    <main class="login-layout">
        <section class="login-story">
            <div class="story-brand"><span class="brand-mark">N</span><strong>Northstar Enrollment Operations</strong></div>
            <div>
                <span class="eyebrow">The clear path forward</span>
                <h1>From first inquiry<br><em>to fully enrolled.</em></h1>
                <p>A focused operations workspace for the people who keep a school moving.</p>
            </div>
            <span class="story-note">Academic year 2026–2027</span>
        </section>
        <section class="login-panel">
            <div class="auth-card">
                <span class="eyebrow">Secure workspace</span>
                <h2><?= htmlspecialchars($roleLabels[$role], ENT_QUOTES, 'UTF-8') ?> Sign In</h2>
                <p class="muted">Sign in to your <?= htmlspecialchars($roleLabels[$role], ENT_QUOTES, 'UTF-8') ?> workspace.</p>
                <?php if ($error): ?>
                    <div class="alert alert-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <form method="post" action="/process_login.php" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="user_role" value="<?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?>">
                    <label>Work email / User ID
                        <input type="email" name="email" autocomplete="username" placeholder="you@northstar.edu" required>
                    </label>
                    <label>Password
                        <span class="password-field">
                            <input id="password" type="password" name="password" autocomplete="current-password" placeholder="Enter your password" required>
                            <button type="button" class="password-toggle" data-toggle-password aria-label="Show password">Show</button>
                        </span>
                    </label>
                    <div class="form-actions">
                        <button type="button" class="text-button" data-open-forgot>Forgot password?</button>
                        <button class="button button-primary" type="submit">Continue to Northstar <span aria-hidden="true">→</span></button>
                    </div>
                </form>
                <p class="auth-help">Your session is protected with school-grade access controls.</p>
            </div>
        </section>
    </main>
    <div class="modal-backdrop" data-forgot-modal hidden>
        <section class="recovery-modal" role="dialog" aria-modal="true" aria-labelledby="recovery-title">
            <button type="button" class="modal-close" data-close-forgot aria-label="Close">×</button>
            <span class="eyebrow">Credential recovery</span>
            <h2 id="recovery-title">Need help signing in?</h2>
            <p class="muted">Contact the registrar's office with your school email and selected workspace role. They can verify your identity and reset your access.</p>
            <a class="button button-primary" href="mailto:registrar@northstar.edu">Email registrar's office</a>
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
