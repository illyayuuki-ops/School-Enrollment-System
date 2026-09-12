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
        $schoolEmail = trim((string) ($_POST['school_email'] ?? ''));
        $levelType = (string) ($_POST['level_type'] ?? '');
        $activeThisYear = isset($_POST['active_this_year']) ? '1' : '0';

        $allowedRoles = ['admin', 'admission', 'registrar', 'teacher', 'student'];
        $allowedLevelTypes = ['BED', 'College', 'TESDA', ''];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $fullName === '' || !in_array($role, $allowedRoles, true) || !in_array($levelType, $allowedLevelTypes, true)) {
            header('Location: /admin/users.php?error=validation');
            exit;
        }

        if ($password === '') {
            $password = bin2hex(random_bytes(8));
        }
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, full_name, role, status, school_email, level_type, active_this_year, created_at) VALUES (:email, :phash, :fname, :role, 'active', :school_email, :level_type, :active_this_year, NOW()) RETURNING id");
            $stmt->execute(['email' => $email, 'phash' => $passwordHash, 'fname' => $fullName, 'role' => $role, 'school_email' => $schoolEmail, 'level_type' => $levelType, 'active_this_year' => $activeThisYear]);
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
        $schoolEmail = trim((string) ($_POST['school_email'] ?? ''));
        $levelType = (string) ($_POST['level_type'] ?? '');
        $activeThisYear = isset($_POST['active_this_year']) ? '1' : '0';

        $allowedRoles = ['admin', 'admission', 'registrar', 'teacher', 'student'];
        $allowedStatuses = ['active', 'suspended'];
        $allowedLevelTypes = ['BED', 'College', 'TESDA', ''];

        if (!$id || !filter_var($email, FILTER_VALIDATE_EMAIL) || $fullName === '' || !in_array($role, $allowedRoles, true) || !in_array($status, $allowedStatuses, true) || !in_array($levelType, $allowedLevelTypes, true)) {
            header('Location: /admin/users.php?error=validation');
            exit;
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE users SET email = :email, full_name = :fname, role = :role, status = :status, school_email = :school_email, level_type = :level_type, active_this_year = :active_this_year WHERE id = :id");
            $stmt->execute(['email' => $email, 'fname' => $fullName, 'role' => $role, 'status' => $status, 'school_email' => $schoolEmail, 'level_type' => $levelType, 'active_this_year' => $activeThisYear, 'id' => $id]);

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
    } elseif ($action === 'reset_password') {
        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) {
            header('Location: /admin/users.php?error=invalid_id');
            exit;
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT email FROM users WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $user = $stmt->fetch();

            if (!$user) {
                $pdo->rollBack();
                header('Location: /admin/users.php?error=user_not_found');
                exit;
            }

            // Guard: admin cannot reset their own password
            if ($id === (int) $_SESSION['user']['id']) {
                $pdo->rollBack();
                header('Location: /admin/users.php?error=cannot_reset_own_password');
                exit;
            }

            $newPassword = bin2hex(random_bytes(8));
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("UPDATE users SET password_hash = :phash WHERE id = :id");
            $stmt->execute(['phash' => $passwordHash, 'id' => $id]);

            $stmt = $pdo->prepare("INSERT INTO system_logs (actor_user_id, action, detail, created_at) VALUES (:actor, 'password_reset', :detail, NOW())");
            $detail = "Reset password for user {$user['email']} (ID {$id})";
            $stmt->execute(['actor' => $_SESSION['user']['id'], 'detail' => $detail]);

            $pdo->commit();
            header('Location: /admin/users.php?success=Password+reset&new_password=' . urlencode($newPassword));
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            header('Location: /admin/users.php?error=Database+error');
            exit;
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) {
            header('Location: /admin/users.php?error=invalid_id');
            exit;
        }

        // Guard: admin cannot delete themselves
        if ($id === (int) $_SESSION['user']['id']) {
            header('Location: /admin/users.php?error=cannot_delete_self');
            exit;
        }

        // Guard: prevent deleting the last admin
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin'");
        $stmt->execute();
        $adminCount = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $targetUser = $stmt->fetch();

        if ($targetUser && $targetUser['role'] === 'admin' && $adminCount <= 1) {
            header('Location: /admin/users.php?error=cannot_delete_last_admin');
            exit;
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT email FROM users WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $user = $stmt->fetch();

            if (!$user) {
                $pdo->rollBack();
                header('Location: /admin/users.php?error=user_not_found');
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
            $stmt->execute(['id' => $id]);

            $stmt = $pdo->prepare("INSERT INTO system_logs (actor_user_id, action, detail, created_at) VALUES (:actor, 'user_deleted', :detail, NOW())");
            $detail = "Deleted user {$user['email']} (ID {$id})";
            $stmt->execute(['actor' => $_SESSION['user']['id'], 'detail' => $detail]);

            $pdo->commit();
            header('Location: /admin/users.php?success=User+deleted+successfully');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            header('Location: /admin/users.php?error=Database+error');
            exit;
        }
    } elseif ($action === 'force_activate') {
        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) {
            header('Location: /admin/users.php?error=invalid_id');
            exit;
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT email, activated FROM users WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $user = $stmt->fetch();

            if (!$user) {
                $pdo->rollBack();
                header('Location: /admin/users.php?error=user_not_found');
                exit;
            }

            if ($user['activated']) {
                $pdo->rollBack();
                header('Location: /admin/users.php?success=User+already+activated');
                exit;
            }

            $stmt = $pdo->prepare("UPDATE users SET activated = TRUE WHERE id = :id");
            $stmt->execute(['id' => $id]);

            $stmt = $pdo->prepare("INSERT INTO system_logs (actor_user_id, action, detail, created_at) VALUES (:actor, 'user_force_activated', :detail, NOW())");
            $detail = "Force activated user {$user['email']} (ID {$id})";
            $stmt->execute(['actor' => $_SESSION['user']['id'], 'detail' => $detail]);

            $pdo->commit();
            header('Location: /admin/users.php?success=User+activated+successfully');
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