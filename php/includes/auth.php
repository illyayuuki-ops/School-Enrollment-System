<?php
declare(strict_types=1);

session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);

const SESSION_IDLE_LIMIT = 600; // 10 minutes in seconds

function home_for_role(string $role): string
{
    $map = [
        'admin' => '/admin/home.php',
        'admission' => '/admission/home.php',
        'registrar' => '/registrar/home.php',
        'teacher' => '/teacher/home.php',
        'student' => '/student/home.php',
    ];
    return $map[$role] ?? '/login/student.php';
}

function login_for_role(?string $role): string
{
    $valid = ['student', 'admin', 'teacher', 'registrar', 'admission'];
    return in_array($role, $valid, true) ? "/login/$role.php" : '/login/student.php';
}

function require_auth(): void
{
    if (!empty($_SESSION['user'])) {
        $now = time();
        if (isset($_SESSION['last_activity']) && ($now - $_SESSION['last_activity']) > SESSION_IDLE_LIMIT) {
            $role = $_SESSION['user']['role'] ?? null;
            $_SESSION = [];
            session_destroy();
            header('Location: ' . login_for_role($role) . '?expired=1');
            exit;
        }
        $_SESSION['last_activity'] = $now;
    }
    if (empty($_SESSION['user'])) {
        header('Location: /login/student.php');
        exit;
    }
}

function require_role(string ...$roles): void
{
    require_auth();
    $activeRole = $_SESSION['user']['role'] ?? '';
    if (!in_array($activeRole, $roles, true)) {
        header('Location: ' . home_for_role($activeRole));
        exit;
    }
}