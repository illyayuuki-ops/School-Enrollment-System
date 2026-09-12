<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_role('admin');
require_once dirname(__DIR__) . '/db_connect.php';

$pdo = db_connect();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/students.php');
    exit;
}

$csrfToken = (string) ($_POST['csrf_token'] ?? '');
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    header('Location: /admin/students.php?error=csrf');
    exit;
}

$action = $_POST['action'] ?? '';

try {
    if ($action === 'create') {
        $studentNumber = trim((string) ($_POST['student_number'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $program = trim((string) ($_POST['program'] ?? ''));
        $yearLevel = (string) ($_POST['year_level'] ?? '');
        $enrollmentStatus = (string) ($_POST['enrollment_status'] ?? 'pending');
        $schoolEmail = trim((string) ($_POST['school_email'] ?? ''));
        $levelType = (string) ($_POST['level_type'] ?? '');
        $activeThisYear = isset($_POST['active_this_year']) ? '1' : '0';
        $password = (string) ($_POST['password'] ?? '');

        $allowedStatuses = ['pending', 'verified', 'enrolled'];
        $allowedLevelTypes = ['BED', 'College', 'TESDA', ''];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $fullName === '' || $studentNumber === '' || $program === '' || $yearLevel === '' || !in_array($enrollmentStatus, $allowedStatuses, true) || !in_array($levelType, $allowedLevelTypes, true)) {
            header('Location: /admin/students.php?error=validation');
            exit;
        }

        if ($password === '') {
            $password = bin2hex(random_bytes(8));
        }
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $pdo->beginTransaction();
        try {
            // Create user
            $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, full_name, role, status, activated, created_at) VALUES (:email, :phash, :fname, 'student', 'active', TRUE, NOW()) RETURNING id");
            $stmt->execute(['email' => $email, 'phash' => password_hash($password, PASSWORD_DEFAULT), 'fname' => $fullName]);
            $userId = $stmt->fetchColumn();

            // Create student record
            $stmt = $pdo->prepare("
                INSERT INTO students (user_id, student_number, enrollment_status, academic_year, program, year_level, school_email, level_type, active_this_year)
                VALUES (:uid, :sn, :enrollment_status, '2026-2027', :program, :year_level, :school_email, :level_type, :active_this_year)
            ");
            $stmt->execute([
                'uid' => $userId,
                'sn' => $studentNumber,
                'enrollment_status' => $enrollmentStatus,
                'program' => $program,
                'year_level' => $yearLevel,
                'school_email' => $schoolEmail,
                'level_type' => $levelType,
                'active_this_year' => $activeThisYear
            ]);

            // Log the creation
            $stmt = $pdo->prepare("INSERT INTO system_logs (actor_user_id, action, detail, created_at) VALUES (:actor, 'student_created', :detail, NOW())");
            $detail = "Created student {$studentNumber} ({$fullName})";
            $stmt->execute(['actor' => $_SESSION['user']['id'], 'detail' => $detail]);

            $pdo->commit();
            header('Location: /admin/students.php?success=Student+created+successfully');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() === '23505') { // unique_violation
                header('Location: /admin/students.php?error=Student+number+or+email+already+exists');
            } else {
                header('Location: /admin/students.php?error=Database+error');
            }
            exit;
        }
    } elseif ($action === 'edit') {
        $id = (int) ($_POST['id'] ?? 0);
        $studentNumber = trim((string) ($_POST['student_number'] ?? ''));
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $program = trim((string) ($_POST['program'] ?? ''));
        $yearLevel = (string) ($_POST['year_level'] ?? '');
        $enrollmentStatus = (string) ($_POST['enrollment_status'] ?? '');
        $schoolEmail = trim((string) ($_POST['school_email'] ?? ''));
        $levelType = (string) ($_POST['level_type'] ?? '');
        $activeThisYear = isset($_POST['active_this_year']) ? '1' : '0';

        $allowedStatuses = ['pending', 'verified', 'enrolled'];
        $allowedLevelTypes = ['BED', 'College', 'TESDA', ''];

        if (!$id || $studentNumber === '' || $fullName === '' || $program === '' || $yearLevel === '' || !in_array($enrollmentStatus, ['pending', 'verified', 'enrolled'], true) || !in_array($levelType, ['BED', 'College', 'TESDA', ''], true)) {
            header('Location: /admin/students.php?error=validation');
            exit;
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE students SET student_number = :sn, program = :program, year_level = :year_level, enrollment_status = :enrollment_status WHERE id = :id");
            $stmt->execute(['sn' => $studentNumber, 'program' => $program, 'year_level' => $yearLevel, 'enrollment_status' => $enrollmentStatus, 'id' => $id]);

            $stmt = $pdo->prepare("UPDATE users SET full_name = :fname, school_email = :school_email, level_type = :level_type, active_this_year = :active_this_year WHERE id = (SELECT user_id FROM students WHERE id = :sid)");
            $stmt->execute(['fname' => $fullName, 'school_email' => $schoolEmail, 'level_type' => $levelType, 'active_this_year' => $activeThisYear ? '1' : '0', 'sid' => $id]);

            if ($stmt->rowCount() > 0) {
                $stmt = $pdo->prepare("INSERT INTO system_logs (actor_user_id, action, detail, created_at) VALUES (:actor, 'student_updated', :detail, NOW())");
                $detail = "Updated student (ID {$id})";
                $stmt->execute(['actor' => $_SESSION['user']['id'], 'detail' => $detail]);
            }

            $pdo->commit();
            header('Location: /admin/students.php?success=Student+updated+successfully');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() === '23505') {
                header('Location: /admin/students.php?error=Student+number+already+exists');
            } else {
                header('Location: /admin/students.php?error=Database+error');
            }
            exit;
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) {
            header('Location: /admin/students.php?error=invalid_id');
            exit;
        }

        $pdo->beginTransaction();
        try {
            // Get student info for logging
            $stmt = $pdo->prepare("SELECT s.student_number, u.full_name FROM students s JOIN users u ON s.user_id = u.id WHERE s.id = :id");
            $stmt->execute(['id' => $id]);
            $student = $stmt->fetch();

            if (!$student) {
                $pdo->rollBack();
                header('Location: /admin/students.php?error=user_not_found');
                exit;
            }

            // Delete student (will cascade to users via foreign key if set, or we need to delete manually)
            $stmt = $pdo->prepare("DELETE FROM students WHERE id = :id");
            $stmt->execute(['id' => $id]);

            // Also delete the user
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = (SELECT user_id FROM students WHERE id = :id)");
            $stmt->execute(['id' => $id]);

            $stmt = $pdo->prepare("INSERT INTO system_logs (actor_user_id, action, detail, created_at) VALUES (:actor, 'student_deleted', :detail, NOW())");
            $detail = "Deleted student {$student['student_number']} ({$student['full_name']})";
            $stmt->execute(['actor' => $_SESSION['user']['id'], 'detail' => $detail]);

            $pdo->commit();
            header('Location: /admin/students.php?success=Student+deleted+successfully');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            header('Location: /admin/students.php?error=Database+error');
            exit;
        }
    }

    header('Location: /admin/students.php?error=unknown_action');
    exit;
} catch (Exception $e) {
    header('Location: /admin/students.php?error=unexpected');
    exit;
}