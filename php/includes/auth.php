<?php
declare(strict_types=1);

session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);

function require_auth(): void
{
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
        http_response_code(403);
        exit('Forbidden');
    }
}