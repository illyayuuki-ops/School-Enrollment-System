<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_role('teacher');
require_once dirname(__DIR__) . '/db_connect.php';
require_once dirname(__DIR__) . '/includes/settings.php';

$pdo = db_connect();
$teacherId = (int) ($_SESSION['user']['id'] ?? 0);

$academicYear = get_setting($pdo, 'academic_year', '2026-2027');
$semester = get_default_semester($pdo);

$classes = [];
try {
    $stmt = $pdo->prepare('SELECT subject_code, subject_name FROM classes_and_schedules WHERE teacher_id = :tid ORDER BY subject_code');
    $stmt->execute(['tid' => $teacherId]);
    $classes = $stmt->fetchAll();
} catch (PDOException $e) {
    // Empty on error
}

$selectedSubject = $_GET['subject'] ?? ($_POST['subject'] ?? '');
$students = [];
$subjectName = '';
$error = null;
$success = false;

if ($selectedSubject) {
    // Verify teacher owns this subject
    $validSubject = false;
    foreach ($classes as $c) {
        if ($c['subject_code'] === $selectedSubject) {
            $validSubject = true;
            $subjectName = $c['subject_name'];
            break;
        }
    }
    if (!$validSubject) {
        $error = 'Invalid subject selected.';
        $selectedSubject = '';
    }
}

if ($selectedSubject && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = (string) ($_POST['csrf_token'] ?? '');
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $error = 'Invalid CSRF token. Please try again.';
    } else {
        // Load existing grades for this subject
        $existingGrades = [];
        try {
            $stmt = $pdo->prepare('SELECT student_id, final_grade, remark FROM student_grades WHERE subject_code = :sc AND academic_year = :ay AND semester = :sem');
            $stmt->execute(['sc' => $selectedSubject, 'ay' => $academicYear, 'sem' => $semester]);
            while ($row = $stmt->fetch()) {
                $existingGrades[$row['student_id']] = ['grade' => $row['final_grade'], 'remark' => $row['remark']];
            }
        } catch (PDOException $e) {}

        // Process grade submissions
        $yearLevel = $_POST['year_level'] ?? '';
        $section = $_POST['section'] ?? '';
        $units = (int) ($_POST['units'] ?? 3);
        $grades = $_POST['grade'] ?? [];
        $remarks = $_POST['remark'] ?? [];

        if (empty($yearLevel) || empty($section)) {
            $error = 'Year level and section are required.';
        } else {
            $pdo->beginTransaction();
            try {
                foreach ($grades as $studentId => $grade) {
                    $grade = trim($grade);
                    $remark = trim($remarks[$studentId] ?? '');
                    if ($grade === '' && $remark === '') {
                        continue;
                    }
                    // Check if record exists
                    $stmt = $pdo->prepare('SELECT 1 FROM student_grades WHERE student_id = :sid AND subject_code = :sc AND academic_year = :ay AND semester = :sem');
                    $stmt->execute(['sid' => $studentId, 'sc' => $selectedSubject, 'ay' => $academicYear, 'sem' => $semester]);
                    if ($stmt->fetch()) {
                        $pdo->prepare('UPDATE student_grades SET final_grade = :g, remark = :r, year_level = :yl, section = :sec WHERE student_id = :sid AND subject_code = :sc AND academic_year = :ay AND semester = :sem')
                            ->execute(['g' => $grade, 'r' => $remark, 'yl' => $yearLevel, 'sec' => $section, 'sid' => $studentId, 'sc' => $selectedSubject, 'ay' => $academicYear, 'sem' => $semester]);
                    } else {
                        $pdo->prepare('INSERT INTO student_grades (student_id, academic_year, semester, year_level, section, subject_code, subject_name, units, final_grade, remark) VALUES (:sid, :ay, :sem, :yl, :sec, :sc, :sn, :u, :g, :r)')
                            ->execute(['sid' => $studentId, 'ay' => $academicYear, 'sem' => $semester, 'yl' => $yearLevel, 'sec' => $section, 'sc' => $selectedSubject, 'sn' => $subjectName, 'u' => $units, 'g' => $grade, 'r' => $remark]);
                    }
                }
                $pdo->commit();
                $success = true;
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = 'An error occurred while saving grades. Please try again.';
                error_log('Grade save error: ' . $e->getMessage());
            }
        }
    }
}

// Load students for the selected subject - only those enrolled in the subject/section
$students = [];
if ($selectedSubject && !$error) {
    try {
        // Get students enrolled in this subject for the current academic year/semester
        $stmt = $pdo->prepare("
            SELECT sc.student_id, s.student_number, u.full_name
            FROM student_courses sc
            JOIN students s ON sc.student_id = s.id
            JOIN users u ON s.user_id = u.id
            WHERE sc.subject_code = :sc 
            AND sc.academic_year = :ay 
            AND sc.semester = :sem
            ORDER BY u.full_name
        ");
        $stmt->execute(['sc' => $selectedSubject, 'ay' => $academicYear, 'sem' => $semester]);
        $enrolledStudents = $stmt->fetchAll();

        // Get existing grades
        $existingGrades = [];
        try {
            $stmt = $pdo->prepare('SELECT student_id, final_grade, remark FROM student_grades WHERE subject_code = :sc AND academic_year = :ay AND semester = :sem');
            $stmt->execute(['sc' => $selectedSubject, 'ay' => $academicYear, 'sem' => $semester]);
            while ($row = $stmt->fetch()) {
                $existingGrades[$row['student_id']] = ['grade' => $row['final_grade'], 'remark' => $row['remark']];
            }
        } catch (PDOException $e) {}

        foreach ($enrolledStudents as $s) {
            $sid = (int) $s['student_id'];
            $students[] = [
                'id' => $sid,
                'student_number' => $s['student_number'],
                'full_name' => $s['full_name'],
                'grade' => $existingGrades[$sid]['grade'] ?? '',
                'remark' => $existingGrades[$sid]['remark'] ?? '',
            ];
        }
    } catch (PDOException $e) {
        // Empty on error
    }
}

$pageTitle = 'Grade Encoding';
$extraStyles = ['/assets/teacher.css'];
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="app-frame">
    <?php require dirname(__DIR__) . '/includes/sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div><span class="eyebrow">Faculty workspace</span><h1>Welcome, <?= htmlspecialchars(explode(' ', $_SESSION['user']['full_name'])[0], ENT_QUOTES, 'UTF-8') ?>.</h1></div>
            <div class="topbar-actions"><span class="year-pill"><?= htmlspecialchars(academic_year_label($pdo)) ?></span><a class="avatar" href="#" onclick="return openLogoutModal()" title="Sign out"><?= strtoupper(substr((string) $_SESSION['user']['full_name'], 0, 1)) ?></a></div>
        </header>
        <section class="content-grid">
            <article class="panel panel-wide">
                <div class="panel-heading"><div><span class="eyebrow">Grade Encoding</span><h2><?= htmlspecialchars($selectedSubject ? $selectedSubject . ' - ' . $subjectName : 'Select a Subject') ?></h2></div></div>

                <?php if ($error): ?>
                    <div class="alert alert-error" style="background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; border-radius: 8px; padding: 12px 16px; margin-bottom: 16px;"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success" style="background: #e4f7f0; color: var(--green); border: 1px solid #b8e5d2; border-radius: 8px; padding: 12px 16px; margin-bottom: 16px;">Grades saved successfully.</div>
                <?php endif; ?>

                <form method="get" action="/teacher/grades.php" style="margin-bottom: 20px;">
                    <div class="form-grid" style="display: grid; grid-template-columns: 1fr auto; gap: 12px; align-items: end;">
                        <div class="form-field">
                            <label for="subject">Subject *</label>
                            <select id="subject" name="subject" required>
                                <option value="">-- Select Subject --</option>
                                <?php foreach ($classes as $c): ?>
                                    <option value="<?= htmlspecialchars($c['subject_code']) ?>" <?= $selectedSubject === $c['subject_code'] ? 'selected' : '' ?>><?= htmlspecialchars($c['subject_code'] . ' - ' . $c['subject_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button class="button button-primary" type="submit" style="height: 40px;">Load</button>
                    </div>
                </form>

                <?php if ($selectedSubject && !$error): ?>
                    <form method="post" action="/teacher/grades.php" class="grade-form" style="margin-top: 16px;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="subject" value="<?= htmlspecialchars($selectedSubject) ?>">

                        <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 12px; align-items: end; margin-bottom: 16px;">
                            <div class="form-field">
                                <label for="year_level">Year Level *</label>
                                <select id="year_level" name="year_level" required>
                                    <option value="">-- Select --</option>
                                    <option value="1st Year">1st Year</option>
                                    <option value="2nd Year">2nd Year</option>
                                    <option value="3rd Year">3rd Year</option>
                                    <option value="4th Year">4th Year</option>
                                    <option value="Grade 7">Grade 7</option>
                                    <option value="Grade 8">Grade 8</option>
                                    <option value="Grade 9">Grade 9</option>
                                    <option value="Grade 10">Grade 10</option>
                                    <option value="Grade 11">Grade 11</option>
                                    <option value="Grade 12">Grade 12</option>
                                    <option value="NC I">NC I</option>
                                    <option value="NC II">NC II</option>
                                    <option value="NC III">NC III</option>
                                </select>
                            </div>
                            <div class="form-field">
                                <label for="section">Section *</label>
                                <input type="text" id="section" name="section" required placeholder="e.g. A, B, 1A">
                            </div>
                            <div class="form-field">
                                <label for="units">Units</label>
                                <input type="number" id="units" name="units" min="1" max="6" value="3" style="width: 80px;">
                            </div>
                        </div>

                        <?php if (empty($students)): ?>
                            <div class="empty-state">
                                <p>No students enrolled in this subject for the current academic year/semester.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-wrapper" style="overflow-x: auto;">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Student #</th>
                                            <th>Name</th>
                                            <th style="text-align: center; width: 100px;">Grade</th>
                                            <th>Remark</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($students as $s): ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars($s['student_number']) ?></strong></td>
                                                <td><?= htmlspecialchars($s['full_name']) ?></td>
                                                <td style="text-align: center;">
                                                    <input type="text" name="grade[<?= (int) $s['id'] ?>]" value="<?= htmlspecialchars($s['grade']) ?>" placeholder="e.g. 1.0, 1.5, 75" class="grade-input" style="padding: 8px; border: 1px solid #ccdcd5; border-radius: 6px; text-align: center; font-size: 13px;">
                                                </td>
                                                <td>
                                                    <input type="text" name="remark[<?= (int) $s['id'] ?>]" value="<?= htmlspecialchars($s['remark']) ?>" placeholder="Optional" class="remark-input" style="padding: 8px; border: 1px solid #ccdcd5; border-radius: 6px; font-size: 13px;">
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <div class="form-actions" style="margin-top: 16px;">
                                    <button class="button button-primary" type="submit">Save Grades <span aria-hidden="true">→</span></button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>
            </article>
        </section>
    </main>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>