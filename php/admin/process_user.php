<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_role('admin');
require_once dirname(__DIR__) . '/db_connect.php';

$pdo = db_connect();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/users.php');
    exit;
}

$csrfToken = (string) ($_POST['csrf_token'] ?? '');
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    header('Location: /admin/users.php?error=csrf');
    exit;
}

$action = $_POST['action'] ?? '';

try {
    if ($action === 'create') {
        $email = trim((string) ($_POST['email'] ?? ''));
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $role = (string) ($_POST['role'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        $allowedRoles = ['admin', 'admission', 'registrar', 'teacher', 'student'];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $fullName === '' || !in_array($role, $allowedRoles, true)) {
            header('Location: /admin/users.php?error=validation');
            exit;
        }

        if ($password === '') {
            $password = bin2hex(random_bytes(8));
        }
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, full_name, role, status, created_at) VALUES (:email, :phash, :fname, :role, 'active', NOW()) RETURNING id");
            $stmt->execute(['email' => $email, 'phash' => $passwordHash, 'fname' => $fullName, 'role' => $role]);
            $newUserId = $stmt->fetchColumn();

            // Log the creation
            $stmt = $pdo->prepare("INSERT INTO system_logs (actor_user_id, action, detail, created_at) VALUES (:actor, 'user_created', :detail, NOW())");
            $detail = "Created user {$email} ({$role})";
            $stmt->execute(['actor' => $_SESSION['user']['id'], 'detail' => $detail]);

            $pdo->commit();
            header('Location: /admin/users.php?success=User+created+successfully');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() === '23505') { // unique_violation
                header('Location: /admin/users.php?error=Email+already+exists');
            } else {
                header('Location: /admin/users.php?error=Database+error');
            }
            exit;
        }
    } elseif ($action === 'edit') {
        $id = (int) ($_POST['id'] ?? 0);
        $email = trim((string) ($_POST['email'] ?? ''));
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $role = (string) ($_POST['role'] ?? '');
        $status = (string) ($_POST['status'] ?? '');

        $allowedRoles = ['admin', 'admission', 'registrar', 'teacher', 'student'];
        $allowedStatuses = ['active', 'suspended'];

        if (!$id || !filter_var($email, FILTER_VALIDATE_EMAIL) || $fullName === '' || !in_array($role, $allowedRoles, true) || !in_array($status, $allowedStatuses, true)) {
            header('Location: /admin/users.php?error=validation');
            exit;
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE users SET email = :email, full_name = :fname, role = :role, status = :status WHERE id = :id");
            $stmt->execute(['email' => $email, 'fname' => $fullName, 'role' => $role, 'status' => $status, 'id' => $id]);

            if ($stmt->rowCount() > 0) {
                $stmt = $pdo->prepare("INSERT INTO system_logs (actor_user_id, action, detail, created_at) VALUES (:actor, 'user_edited', :detail, NOW())");
                $detail = "Edited user {$email} (ID {$id})";
                $stmt->execute(['actor' => $_SESSION['user']['id'], 'detail' => $detail]);
            }

            $pdo->commit();
            header('Location: /admin/users.php?success=User+updated+successfully');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() === '23505') {
                header('Location: /admin/users.php?error=Email+already+exists');
            } else {
                header('Location: /admin/users.php?error=Database+error');
            }
            exit;
        }
    } elseif ($action === 'toggle_status') {
        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) {
            header('Location: /admin/users.php?error=invalid_id');
            exit;
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT email, status FROM users WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $user = $stmt->fetch();

            if ($user) {
                $newStatus = $user['status'] === 'active' ? 'suspended' : 'active';
                $stmt = $pdo->prepare("UPDATE users SET status = :status WHERE id = :id");
                $stmt->execute(['status' => $newStatus, 'id' => $id]);

                $stmt = $pdo->prepare("INSERT INTO system_logs (actor_user_id, action, detail, created_at) VALUES (:actor, 'user_status_changed', :detail, NOW())");
                $detail = "User {$user['email']} (ID {$id}) status changed to {$newStatus}";
                $stmt->execute(['actor' => $_SESSION['user']['id'], 'detail' => $detail]);
            }

            $pdo->commit();
            header('Location: /admin/users.php?success=User+status+updated');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            header('Location: /admin/users.php?error=Database+error');
            exit;
        }
    }

    header('Location: /admin/users.php?error=unknown_action');
    exit;
} catch (Exception $e) {
    header('Location: /admin/users.php?error=unexpected');
    exit;
}