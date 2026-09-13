<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_role('registrar');
require_once dirname(__DIR__) . '/db_connect.php';

$pdo = db_connect();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /registrar/sections.php');
    exit;
}

$csrfToken = (string) ($_POST['csrf_token'] ?? '');
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    header('Location: /registrar/sections.php?error=csrf');
    exit;
}

$action = $_POST['action'] ?? '';

try {
    if ($action === 'create') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $levelType = (string) ($_POST['level_type'] ?? '');
        $program = trim((string) ($_POST['program'] ?? ''));
        $yearLevel = trim((string) ($_POST['year_level'] ?? ''));
        $adviserId = (int) ($_POST['adviser_id'] ?? 0);
        $capacity = (int) ($_POST['capacity'] ?? 40);
        $academicYear = trim((string) ($_POST['academic_year'] ?? '2026-2027'));

        $allowedLevelTypes = ['BED', 'College', 'TESDA'];
        if ($name === '' || !in_array($levelType, $allowedLevelTypes, true) || $program === '' || $yearLevel === '' || $capacity < 1) {
            header('Location: /registrar/sections.php?error=validation');
            exit;
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                INSERT INTO sections (name, level_type, program, year_level, adviser_id, capacity, academic_year, created_at)
                VALUES (:name, :level_type, :program, :year_level, :adviser_id, :capacity, :academic_year, NOW())
                RETURNING id
            ");
            $stmt->execute([
                'name' => $name,
                'level_type' => $levelType,
                'program' => $program,
                'year_level' => $yearLevel,
                'adviser_id' => $adviserId ?: null,
                'capacity' => $capacity,
                'academic_year' => $academicYear,
            ]);
            $sectionId = $stmt->fetchColumn();

            $stmt = $pdo->prepare("INSERT INTO system_logs (actor_user_id, action, detail, created_at) VALUES (:actor, 'section_created', :detail, NOW())");
            $detail = "Created section {$name} ({$levelType} {$yearLevel}) with capacity {$capacity}";
            $stmt->execute(['actor' => $_SESSION['user']['id'], 'detail' => $detail]);

            $pdo->commit();
            header('Location: /registrar/sections.php?success=Section+created+successfully');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            header('Location: /registrar/sections.php?error=Database+error');
            exit;
        }
    } elseif ($action === 'edit') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $levelType = (string) ($_POST['level_type'] ?? '');
        $program = trim((string) ($_POST['program'] ?? ''));
        $yearLevel = trim((string) ($_POST['year_level'] ?? ''));
        $adviserId = (int) ($_POST['adviser_id'] ?? 0);
        $capacity = (int) ($_POST['capacity'] ?? 40);
        $academicYear = trim((string) ($_POST['academic_year'] ?? ''));

        $allowedLevelTypes = ['BED', 'College', 'TESDA'];
        if (!$id || $name === '' || !in_array($levelType, ['BED', 'College', 'TESDA'], true) || $program === '' || $yearLevel === '' || $capacity < 1) {
            header('Location: /registrar/sections.php?error=validation');
            exit;
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE sections SET name = :name, level_type = :level_type, program = :program, year_level = :year_level, adviser_id = :adviser_id, capacity = :capacity, academic_year = :academic_year WHERE id = :id");
            $stmt->execute(['name' => $name, 'level_type' => $levelType, 'program' => $program, 'year_level' => $yearLevel, 'adviser_id' => $adviserId ?: null, 'capacity' => $capacity, 'academic_year' => $academicYear, 'id' => $id]);

            if ($stmt->rowCount() > 0) {
                $stmt = $pdo->prepare("INSERT INTO system_logs (actor_user_id, action, detail, created_at) VALUES (:actor, 'section_updated', :detail, NOW())");
                $detail = "Updated section ID {$id} ({$name})";
                $stmt->execute(['actor' => $_SESSION['user']['id'], 'detail' => $detail]);
            }

            $pdo->commit();
            header('Location: /registrar/sections.php?success=Section+updated+successfully');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            header('Location: /registrar/sections.php?error=Database+error');
            exit;
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) {
            header('Location: /registrar/sections.php?error=invalid_id');
            exit;
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT name FROM sections WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $section = $stmt->fetch();

            if (!$section) {
                $pdo->rollBack();
                header('Location: /registrar/sections.php?error=not_found');
                exit;
            }

            // Check if any students are assigned
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE section_id = :id");
            $stmt->execute(['id' => $id]);
            $studentCount = (int) $stmt->fetchColumn();
            if ($studentCount > 0) {
                $pdo->rollBack();
                header('Location: /registrar/sections.php?error=Section+has+assigned+students');
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM sections WHERE id = :id");
            $stmt->execute(['id' => $id]);

            $stmt = $pdo->prepare("INSERT INTO system_logs (actor_user_id, action, detail, created_at) VALUES (:actor, 'section_deleted', :detail, NOW())");
            $detail = "Deleted section {$section['name']} (ID {$id})";
            $stmt->execute(['actor' => $_SESSION['user']['id'], 'detail' => $detail]);

            $pdo->commit();
            header('Location: /registrar/sections.php?success=Section+deleted+successfully');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            header('Location: /registrar/sections.php?error=Database+error');
            exit;
        }
    } elseif ($action === 'assign_student') {
        $sectionId = (int) ($_POST['section_id'] ?? 0);
        $studentId = (int) ($_POST['student_id'] ?? 0);

        if (!$sectionId || !$studentId) {
            header('Location: /registrar/sections.php?error=validation');
            exit;
        }

        $pdo->beginTransaction();
        try {
            // Check section capacity
            $stmt = $pdo->prepare("SELECT capacity, (SELECT COUNT(*) FROM students WHERE section_id = :id) as current_count FROM sections WHERE id = :id");
            $stmt->execute(['id' => $sectionId]);
            $section = $stmt->fetch();

            if (!$section) {
                $pdo->rollBack();
                header('Location: /registrar/sections.php?error=section_not_found');
                exit;
            }

            if ((int)$section['current_count'] >= (int)$section['capacity']) {
                $pdo->rollBack();
                header('Location: /registrar/sections.php?error=Section+at+capacity');
                exit;
            }

            $stmt = $pdo->prepare("UPDATE students SET section_id = :section_id WHERE id = :id");
            $stmt->execute(['section_id' => $sectionId, 'id' => $studentId]);

            $stmt = $pdo->prepare("INSERT INTO system_logs (actor_user_id, action, detail, created_at) VALUES (:actor, 'student_section_assigned', :detail, NOW())");
            $detail = "Assigned student ID {$studentId} to section ID {$sectionId}";
            $stmt->execute(['actor' => $_SESSION['user']['id'], 'detail' => $detail]);

            $pdo->commit();
            header('Location: /registrar/sections.php?success=Student+assigned+to+section');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            header('Location: /registrar/sections.php?error=Database+error');
            exit;
        }
    } elseif ($action === 'unassign_student') {
        $studentId = (int) ($_POST['student_id'] ?? 0);
        if (!$studentId) {
            header('Location: /registrar/sections.php?error=validation');
            exit;
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE students SET section_id = NULL WHERE id = :id");
            $stmt->execute(['id' => $studentId]);

            $stmt = $pdo->prepare("INSERT INTO system_logs (actor_user_id, action, detail, created_at) VALUES (:actor, 'student_section_unassigned', :detail, NOW())");
            $detail = "Unassigned student ID {$studentId} from section";
            $stmt->execute(['actor' => $_SESSION['user']['id'], 'detail' => $detail]);

            $pdo->commit();
            header('Location: /registrar/sections.php?success=Student+unassigned+from+section');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            header('Location: /registrar/sections.php?error=Database+error');
            exit;
        }
    }

    header('Location: /registrar/sections.php?error=unknown_action');
    exit;
} catch (Exception $e) {
    header('Location: /registrar/sections.php?error=unexpected');
    exit;
}