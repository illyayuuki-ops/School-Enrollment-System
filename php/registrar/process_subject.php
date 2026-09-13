<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_role('registrar');
require_once dirname(__DIR__) . '/db_connect.php';

$pdo = db_connect();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /registrar/subjects.php');
    exit;
}

$csrfToken = (string) ($_POST['csrf_token'] ?? '');
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    header('Location: /registrar/subjects.php?error=csrf');
    exit;
}

$action = $_POST['action'] ?? '';

try {
    if ($action === 'create') {
        $subjectCode = trim((string) ($_POST['subject_code'] ?? ''));
        $subjectName = trim((string) ($_POST['subject_name'] ?? ''));
        $teacherId = (int) ($_POST['teacher_id'] ?? 0);
        $scheduleGrid = trim((string) ($_POST['schedule_grid'] ?? ''));
        $room = trim((string) ($_POST['room'] ?? ''));
        $capacity = (int) ($_POST['capacity'] ?? 0);
        $units = (int) ($_POST['units'] ?? 0);
        $adviserSection = trim((string) ($_POST['adviser_section'] ?? ''));

        if ($subjectCode === '' || $subjectName === '' || $capacity < 1 || $units < 0) {
            header('Location: /registrar/subjects.php?error=validation');
            exit;
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                INSERT INTO classes_and_schedules (subject_code, subject_name, teacher_id, schedule_grid, room, capacity, enrolled_count, units, adviser_section, created_at)
                VALUES (:code, :name, :tid, :schedule, :room, :cap, 0, :units, :adviser, NOW())
                RETURNING id
            ");
            $stmt->execute([
                'code' => $subjectCode,
                'name' => $subjectName,
                'tid' => $teacherId ?: null,
                'schedule' => $scheduleGrid,
                'room' => $room,
                'cap' => $capacity,
                'units' => $units ?: null,
                'adviser' => $adviserSection ?: null,
            ]);
            $newId = $stmt->fetchColumn();

            $stmt = $pdo->prepare("INSERT INTO system_logs (actor_user_id, action, detail, created_at) VALUES (:actor, 'subject_created', :detail, NOW())");
            $detail = "Created subject {$subjectCode} - {$subjectName}";
            $stmt->execute(['actor' => $_SESSION['user']['id'], 'detail' => $detail]);

            $pdo->commit();
            header('Location: /registrar/subjects.php?success=Subject+created+successfully');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() === '23505') {
                header('Location: /registrar/subjects.php?error=Subject+code+already+exists');
            } else {
                header('Location: /registrar/subjects.php?error=Database+error');
            }
            exit;
        }
    } elseif ($action === 'edit') {
        $id = (int) ($_POST['id'] ?? 0);
        $subjectCode = trim((string) ($_POST['subject_code'] ?? ''));
        $subjectName = trim((string) ($_POST['subject_name'] ?? ''));
        $teacherId = (int) ($_POST['teacher_id'] ?? 0);
        $scheduleGrid = trim((string) ($_POST['schedule_grid'] ?? ''));
        $room = trim((string) ($_POST['room'] ?? ''));
        $capacity = (int) ($_POST['capacity'] ?? 0);
        $units = (int) ($_POST['units'] ?? 0);
        $adviserSection = trim((string) ($_POST['adviser_section'] ?? ''));

        if (!$id || $subjectCode === '' || $subjectName === '' || $capacity < 1 || $units < 0) {
            header('Location: /registrar/subjects.php?error=validation');
            exit;
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE classes_and_schedules SET subject_code = :code, subject_name = :name, teacher_id = :tid, schedule_grid = :schedule, room = :room, capacity = :cap, units = :units, adviser_section = :adviser WHERE id = :id");
            $stmt->execute(['code' => $subjectCode, 'name' => $subjectName, 'tid' => $teacherId ?: null, 'schedule' => $scheduleGrid, 'room' => $room, 'cap' => $capacity, 'units' => $units ?: null, 'adviser' => $adviserSection ?: null, 'id' => $id]);

            if ($stmt->rowCount() > 0) {
                $stmt = $pdo->prepare("INSERT INTO system_logs (actor_user_id, action, detail, created_at) VALUES (:actor, 'subject_updated', :detail, NOW())");
                $detail = "Updated subject ID {$id} ({$subjectCode})";
                $stmt->execute(['actor' => $_SESSION['user']['id'], 'detail' => $detail]);
            }

            $pdo->commit();
            header('Location: /registrar/subjects.php?success=Subject+updated+successfully');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() === '23505') {
                header('Location: /registrar/subjects.php?error=Subject+code+already+exists');
            } else {
                header('Location: /registrar/subjects.php?error=Database+error');
            }
            exit;
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) {
            header('Location: /registrar/subjects.php?error=invalid_id');
            exit;
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT subject_code, subject_name FROM classes_and_schedules WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $subject = $stmt->fetch();

            if (!$subject) {
                $pdo->rollBack();
                header('Location: /registrar/subjects.php?error=not_found');
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM classes_and_schedules WHERE id = :id");
            $stmt->execute(['id' => $id]);

            $stmt = $pdo->prepare("INSERT INTO system_logs (actor_user_id, action, detail, created_at) VALUES (:actor, 'subject_deleted', :detail, NOW())");
            $detail = "Deleted subject {$subject['subject_code']} - {$subject['subject_name']} (ID {$id})";
            $stmt->execute(['actor' => $_SESSION['user']['id'], 'detail' => $detail]);

            $pdo->commit();
            header('Location: /registrar/subjects.php?success=Subject+deleted+successfully');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            header('Location: /registrar/subjects.php?error=Database+error');
            exit;
        }
    }

    header('Location: /registrar/subjects.php?error=unknown_action');
    exit;
} catch (Exception $e) {
    header('Location: /registrar/subjects.php?error=unexpected');
    exit;
}