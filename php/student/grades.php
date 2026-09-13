<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_role('student');
require_once dirname(__DIR__) . '/db_connect.php';
require_once dirname(__DIR__) . '/includes/settings.php';

$pdo = db_connect();
$studentId = (int) ($_SESSION['user']['id'] ?? 0);

$academicYear = get_academic_year($pdo);

// Get student record
$student = null;
$grades = [];
try {
    $stmt = $pdo->prepare("SELECT id, student_number, program, year_level FROM students WHERE user_id = :uid");
    $stmt->execute(['uid' => $studentId]);
    $student = $stmt->fetch();

    if ($student) {
        $stmt = $pdo->prepare("
            SELECT sg.*, 
                   CASE 
                       WHEN final_grade IN ('1.0','1.25','1.5','1.75','2.0','2.25','2.25','2.5','2.75','3.0') THEN 'passed'
                       WHEN final_grade IN ('3.5','4.0','5.0','INC','DROPPED') THEN 'failed'
                       ELSE 'pending'
                   END as grade_status
            FROM student_grades sg
            WHERE sg.student_id = :sid
            ORDER BY sg.academic_year DESC, sg.semester, sg.subject_code
        ");
        $stmt->execute(['sid' => $student['id']]);
        $grades = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    // Keep defaults on error
}

$pageTitle = 'Grades';
$extraStyles = ['/assets/student.css'];
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="app-frame">
    <?php require dirname(__DIR__) . '/includes/sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div><span class="eyebrow">Student workspace</span><h1>Welcome, <?= htmlspecialchars(explode(' ', $_SESSION['user']['full_name'])[0], ENT_QUOTES, 'UTF-8') ?>.</h1></div>
            <div class="topbar-actions"><span class="year-pill"><?= htmlspecialchars(get_academic_year($pdo)) ?></span><a class="avatar" href="#" onclick="return openLogoutModal()" title="Sign out"><?= strtoupper(substr((string) $_SESSION['user']['full_name'], 0, 1)) ?></a></div>
        </header>

        <section class="content-grid">
            <article class="panel panel-wide">
                <div class="panel-heading"><div><span class="eyebrow">Academics</span><h2>Grades</h2></div></div>

                <?php if (empty($grades)): ?>
                    <div class="empty-state">
                        <p>No grades recorded yet.</p>
                    </div>
                <?php else: ?>
                    <?php
                    // Group by academic_year then semester
                    $grouped = [];
                    foreach ($grades as $g) {
                        $ay = $g['academic_year'] ?? 'Unknown';
                        $sem = $g['semester'] ?? 'Unknown';
                        $grouped[$ay][$sem][] = $g;
                    }
                    krsort($grouped);
                    foreach ($grouped as $ay => $semesters):
                    ?>
                        <div style="margin-top: 24px;">
                            <h3 style="font-size: 14px; font-weight: 700; color: var(--navy); margin-bottom: 16px; padding-bottom: 8px; border-bottom: 1px solid var(--line);">Academic Year: <?= htmlspecialchars($ay) ?></h3>
                            <?php foreach ($semesters as $sem => $grades): ?>
                                <h4 style="font-size: 13px; font-weight: 600; color: var(--navy); margin: 16px 0 8px;"><?= htmlspecialchars($sem) ?></h4>
                                <div class="table-wrapper" style="overflow-x: auto;">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th>Subject Code</th>
                                                <th>Subject Name</th>
                                                <th style="text-align: center;">Units</th>
                                                <th style="text-align: center;">Final Grade</th>
                                                <th>Remark</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($grades as $g): ?>
                                                <?php
                                                $status = $g['grade_status'] ?? 'pending';
                                                $gradeClass = $status === 'passed' ? 'pass' : ($status === 'failed' ? 'fail' : 'pending');
                                                ?>
                                                <tr class="grade-row grade-<?= $status ?>">
                                                    <td><strong><?= htmlspecialchars($g['subject_code']) ?></strong></td>
                                                    <td><?= htmlspecialchars($g['subject_name']) ?></td>
                                                    <td style="text-align: center;"><?= (int) $g['units'] ?></td>
                                                    <td class="grade-cell <?= $gradeClass ?>" style="text-align: center;">
                                                        <?= htmlspecialchars($g['final_grade'] ?? '—') ?>
                                                    </td>
                                                    <td><?= htmlspecialchars($g['remark'] ?? '—') ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </article>
        </section>
    </main>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>