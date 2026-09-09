<?php
declare(strict_types=1);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/auth.php';

if (!empty($_SESSION['user'])) {
    header('Location: /dashboard.php');
    exit;
}

$error = null;
$selectedRole = $_POST['role'] ?? 'student';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $allowedRoles = ['admin', 'admission', 'registrar', 'teacher', 'student'];

    if (!in_array($selectedRole, $allowedRoles, true) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email and role.';
    } else {
        $statement = db_connect()->prepare(
            'SELECT id, email, full_name, password_hash, role, status FROM users WHERE email = :email AND role = :role LIMIT 1'
        );
        $statement->execute(['email' => $email, 'role' => $selectedRole]);
        $user = $statement->fetch();

        if (!$user || $user['status'] !== 'active' || !password_verify($password, $user['password_hash'])) {
            $error = 'Those credentials could not be verified.';
        } else {
            session_regenerate_id(true);
            unset($user['password_hash']);
            $_SESSION['user'] = $user;
            header('Location: /dashboard.php');
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · Northstar Enrollment</title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body class="login-page">
    <main class="login-layout">
        <section class="login-story">
            <div class="story-brand"><span class="brand-mark">N</span><strong>Northstar</strong></div>
            <div>
                <span class="eyebrow">Enrollment operations</span>
                <h1>Move every learner<br><em>forward.</em></h1>
                <p>A calm, connected workspace for the people who make a school year happen.</p>
            </div>
            <span class="story-note">Built for better starts · A.Y. 2026–2027</span>
        </section>
        <section class="login-panel">
            <div class="auth-card">
                <span class="eyebrow">Secure portal</span>
                <h2>Welcome back</h2>
                <p class="muted">Sign in to continue to your enrollment workspace.</p>
                <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                <form method="post" novalidate>
                    <label>Email address<input type="email" name="email" autocomplete="username" placeholder="you@northstar.edu" required></label>
                    <label>Password<input type="password" name="password" autocomplete="current-password" placeholder="Enter your password" required></label>
                    <label>Workspace role<select name="role">
                        <?php foreach (['student', 'admission', 'registrar', 'teacher', 'admin'] as $role): ?>
                            <option value="<?= $role ?>" <?= $selectedRole === $role ? 'selected' : '' ?>><?= ucfirst($role) ?></option>
                        <?php endforeach; ?>
                    </select></label>
                    <button class="button button-primary" type="submit">Sign in <span aria-hidden="true">↗</span></button>
                </form>
                <p class="auth-help">Having trouble? <a href="mailto:registrar@northstar.edu">Contact support</a></p>
            </div>
        </section>
    </main>
</body>
</html>