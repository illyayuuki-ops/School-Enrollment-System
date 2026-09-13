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
$courses = [];
try {
    $stmt = $pdo->prepare("SELECT student_number, program, year_level FROM students WHERE user_id = :uid");
    $stmt->execute(['uid' => $studentId]);
    $student = $stmt->fetch();

    if ($student) {
        $stmt = $pdo->prepare("
            SELECT subject_code, subject_name, units, semester, status
            FROM student_courses
            WHERE student_id = :sid
            ORDER BY semester, subject_code
        ");
        $stmt->execute(['sid' => $student['student_number']]);
        $courses = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    // Keep defaults on error
}

$pageTitle = 'Study Load';
$extraStyles = ['/assets/student.css'];
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="app-frame">
    <?php require dirname(__DIR__) . '/includes/sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div><span class="eyebrow">Student workspace</span><h1>Welcome, <?= htmlspecialchars(explode(' ', $_SESSION['user']['full_name'])[0], ENT_QUOTES, 'UTF-8') ?>.</h1></div>
            <div class="topbar-actions"><span class="year-pill"><?= htmlspecialchars($academicYear) ?></span><a class="avatar" href="#" onclick="return openLogoutModal()" title="Sign out"><?= strtoupper(substr((string) $_SESSION['user']['full_name'], 0, 1)) ?></a></div>
        </header>
        <section class="content-grid">
            <article class="panel panel-wide">
                <div class="panel-heading"><div><span class="eyebrow">Study Load</span><h2>Current Schedule</h2></div></div>
                
                <?php if (empty($courses)): ?>
                    <div class="empty-state">
                        <p>No enrolled courses found for the current term.</p>
                    </div>
                <?php else: ?>
                    <div class="table-wrapper" style="overflow-x: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Subject Code</th>
                                    <th>Subject Name</th>
                                    <th>Units</th>
                                    <th>Semester</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($courses as $course): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($course['subject_code']) ?></strong></td>
                                        <td><?= htmlspecialchars($course['subject_name']) ?></td>
                                        <td style="text-align: center;"><?= (int) $course['units'] ?></td>
                                        <td><?= htmlspecialchars($course['semester']) ?></td>
                                        <td>
                                            <span class="status-badge status-<?= $course['status'] === 'enrolled' ? 'paid' : ($course['status'] === 'pending' ? 'partial' : 'unpaid') ?>" style="font-size: 11px; text-transform: capitalize;">
                                                <?= htmlspecialchars(ucfirst($course['status'])) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </article>
        </section>
    </main>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>