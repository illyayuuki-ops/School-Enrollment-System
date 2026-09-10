<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (!empty($_SESSION['user'])) {
    header('Location: /dashboard.php');
    exit;
}
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
                <h2>Sign in to your workspace</h2>
                <p class="muted">Choose your workspace to continue.</p>
                <ul class="role-links">
                    <li><a href="/login/student.php">Student</a></li>
                    <li><a href="/login/admin.php">Administrator</a></li>
                    <li><a href="/login/teacher.php">Faculty</a></li>
                    <li><a href="/login/registrar.php">Registrar</a></li>
                    <li><a href="/login/admission.php">Admissions</a></li>
                </ul>
                <p class="auth-help">Your session is protected with school-grade access controls.</p>
            </div>
        </section>
    </main>
</body>
</html>
