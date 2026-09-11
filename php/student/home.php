<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_role('student');
require_once dirname(__DIR__) . '/db_connect.php';

$pdo = db_connect();
$studentId = (int) ($_SESSION['user']['id'] ?? 0);

// Get student record
$student = null;
$ledger = [];
$totalDue = 0.00;
$totalPaid = 0.00;
try {
    $stmt = $pdo->prepare("SELECT student_number, program, year_level FROM students WHERE user_id = :uid");
    $stmt->execute(['uid' => $studentId]);
    $student = $stmt->fetch();

    if ($student) {
        $stmt = $pdo->prepare("
            SELECT id, academic_year, description, amount_due, amount_paid, status, created_at
            FROM student_ledgers
            WHERE student_id = :sid
            ORDER BY created_at DESC
        ");
        $stmt->execute(['sid' => $student['student_number']]);
        $ledger = $stmt->fetchAll();

        $totalDue = array_sum(array_column($ledger, 'amount_due'));
        $totalPaid = array_sum(array_column($ledger, 'amount_paid'));
    }
} catch (PDOException $e) {
    // Keep defaults on error
}

$balance = $totalDue - $totalPaid;
$enrolledCourses = 0;
$pendingTasks = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM classes_and_schedules WHERE teacher_id = :tid");
    // For student, we'd query student_courses or similar, but keep draft placeholders
} catch (PDOException $e) {}

$pageTitle = 'Student Overview';
$extraStyles = ['/assets/registrar.css'];
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="app-frame">
    <?php require dirname(__DIR__) . '/includes/sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div><span class="eyebrow">Student workspace</span><h1>Good morning, <?= htmlspecialchars(explode(' ', $_SESSION['user']['full_name'])[0], ENT_QUOTES, 'UTF-8') ?>.</h1></div>
            <div class="topbar-actions"><span class="year-pill">A.Y. 2026–2027</span><a class="avatar" href="/logout.php" title="Sign out"><?= strtoupper(substr((string) $_SESSION['user']['full_name'], 0, 1)) ?></a></div>
        </header>

        <?php if ($student): ?>
            <!-- Balance Summary -->
            <div class="student-balance-summary" style="margin: 24px auto; max-width: 800px;">
                <div class="summary-title">Account Balance (A.Y. 2026–2027)</div>
                <div class="summary-row">
                    <span class="summary-label">Total Assessed</span>
                    <span class="summary-value due">₱<?= number_format($totalDue, 2) ?></span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Total Paid</span>
                    <span class="summary-value paid">₱<?= number_format($totalPaid, 2) ?></span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Outstanding Balance</span>
                    <span class="summary-value balance <?= $balance > 0 ? 'due' : '' ?>">₱<?= number_format($balance, 2) ?></span>
                </div>
            </div>
        <?php endif; ?>

        <section class="dashboard-grid">
            <article class="metric-card"><span class="eyebrow">Enrolled courses</span><strong><?= $enrolledCourses ?: '—' ?></strong><small>Draft placeholder</small></article>
            <article class="metric-card"><span class="eyebrow">Pending tasks</span><strong><?= $pendingTasks ?: '—' ?></strong><small>Draft placeholder</small></article>
            <article class="metric-card"><span class="eyebrow">GPA</span><strong>—</strong><small>Draft placeholder</small></article>
            <article class="metric-card accent-card"><span class="eyebrow">Attendance</span><strong>—</strong><small>Draft placeholder</small><div class="progress"><span style="width:0%"></span></div></article>
        </section>
        <section class="content-grid">
            <article class="panel panel-wide">
                <div class="panel-heading"><div><span class="eyebrow">Quick actions</span><h2>Stay on track</h2></div></div>
                <div class="action-list">
                    <a href="/student/enrollment.php"><span class="action-icon blue">01</span><span><strong>Enrollment wizard</strong><small>Select courses for next term</small></span><span class="arrow">↗</span></a>
                    <a href="/student/load.php"><span class="action-icon green">02</span><span><strong>Study load</strong><small>View current schedule and units</small></span><span class="arrow">↗</span></a>
                    <a href="/student/status.php"><span class="action-icon amber">03</span><span><strong>Application status</strong><small>Check pending requests</small></span><span class="arrow">↗</span></a>
                </div>
            </article>
            <article class="panel">
                <div class="panel-heading"><div><span class="eyebrow">Recent activity</span><h2>What's new</h2></div></div>
                <ul class="activity-list">
                    <li><span class="activity-dot green"></span><span><strong>Course enrolled</strong><small>CS101 · 2 hrs ago</small></span></li>
                    <li><span class="activity-dot blue"></span><span><strong>Grade posted</strong><small>MATH201 · 1 day ago</small></span></li>
                    <li><span class="activity-dot amber"></span><span><strong>Payment due</strong><small>Tuition · 3 days ago</small></span></li>
                </ul>
            </article>
        </section>
    </main>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>