<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_role('admin');
require_once dirname(__DIR__) . '/db_connect.php';

$pdo = db_connect();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/teacher_attendance.php');
    exit;
}

$csrfToken = (string) ($_POST['csrf_token'] ?? '');
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    header('Location: /admin/teacher_attendance.php?error=csrf');
    exit;
}

$action = $_POST['action'] ?? '';

try {
    if ($action === 'create') {
        $teacherId = (int) ($_POST['teacher_id'] ?? 0);
        $workDate = (string) ($_POST['work_date'] ?? '');
        $status = (string) ($_POST['status'] ?? '');
        $remarks = trim((string) ($_POST['remarks'] ?? ''));

        $allowedStatuses = ['present', 'absent', 'late', 'leave'];
        if (!$teacherId || $workDate === '' || !in_array($status, $allowedStatuses, true)) {
            header('Location: /admin/teacher_attendance.php?error=validation');
            exit;
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                INSERT INTO teacher_attendance (teacher_id, work_date, status, remarks, recorded_by, created_at)
                VALUES (:tid, :wd, :status, :remarks, :rb, NOW())
                ON CONFLICT (teacher_id, work_date) DO UPDATE SET
                    status = EXCLUDED.status,
                    remarks = EXCLUDED.remarks,
                    recorded_by = EXCLUDED.recorded_by,
                    created_at = EXCLUDED.created_at
            ");
            $stmt->execute([
                'tid' => $teacherId,
                'wd' => $workDate,
                'status' => $status,
                'remarks' => $remarks,
                'rb' => $_SESSION['user']['id'],
            ]);

            // Log the creation
            $stmt = $pdo->prepare("INSERT INTO system_logs (actor_user_id, action, detail, created_at) VALUES (:actor, 'teacher_attendance_recorded', :detail, NOW())");
            $detail = "Recorded attendance for teacher ID {$teacherId} on {$workDate} as {$status}";
            $stmt->execute(['actor' => $_SESSION['user']['id'], 'detail' => $detail]);

            $pdo->commit();
            header('Location: /admin/teacher_attendance.php?success=Attendance+recorded+successfully');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            header('Location: /admin/teacher_attendance.php?error=Database+error');
            exit;
        }
    } elseif ($action === 'edit') {
        $id = (int) ($_POST['id'] ?? 0);
        $teacherId = (int) ($_POST['teacher_id'] ?? 0);
        $workDate = (string) ($_POST['work_date'] ?? '');
        $status = (string) ($_POST['status'] ?? '');
        $remarks = trim((string) ($_POST['remarks'] ?? ''));

        $allowedStatuses = ['present', 'absent', 'late', 'leave'];
        if (!$id || !$teacherId || $workDate === '' || !in_array($status, ['present', 'absent', 'late', 'leave'], true)) {
            header('Location: /admin/teacher_attendance.php?error=validation');
            exit;
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE teacher_attendance SET teacher_id = :tid, work_date = :wd, status = :status, remarks = :remarks WHERE id = :id");
            $stmt->execute(['tid' => $teacherId, 'wd' => $workDate, 'status' => $status, 'remarks' => $remarks, 'id' => $id]);

            if ($stmt->rowCount() > 0) {
                $stmt = $pdo->prepare("INSERT INTO system_logs (actor_user_id, action, detail, created_at) VALUES (:actor, 'teacher_attendance_updated', :detail, NOW())");
                $detail = "Updated attendance record (ID {$id}) for teacher ID {$teacherId} on {$workDate} to {$status}";
                $stmt->execute(['actor' => $_SESSION['user']['id'], 'detail' => $detail]);
            }

            $pdo->commit();
            header('Location: /admin/teacher_attendance.php?success=Attendance+updated+successfully');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            header('Location: /admin/teacher_attendance.php?error=Database+error');
            exit;
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) {
            header('Location: /admin/teacher_attendance.php?error=invalid_id');
            exit;
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT teacher_id, work_date, status FROM teacher_attendance WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $attendance = $stmt->fetch();

            if (!$attendance) {
                $pdo->rollBack();
                header('Location: /admin/teacher_attendance.php?error=not_found');
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM teacher_attendance WHERE id = :id");
            $stmt->execute(['id' => $id]);

            $stmt = $pdo->prepare("INSERT INTO system_logs (actor_user_id, action, detail, created_at) VALUES (:actor, 'teacher_attendance_deleted', :detail, NOW())");
            $detail = "Deleted attendance record for teacher ID {$attendance['teacher_id']} on {$attendance['work_date']} (was {$attendance['status']})";
            $stmt->execute(['actor' => $_SESSION['user']['id'], 'detail' => $detail]);

            $pdo->commit();
            header('Location: /admin/teacher_attendance.php?success=Attendance+record+deleted+successfully');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            header('Location: /admin/teacher_attendance.php?error=Database+error');
            exit;
        }
    }

    header('Location: /admin/teacher_attendance.php?error=unknown_action');
    exit;
} catch (Exception $e) {
    header('Location: /admin/teacher_attendance.php?error=unexpected');
    exit;
}