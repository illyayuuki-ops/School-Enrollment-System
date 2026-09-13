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
$ledger = [];
$totalDue = 0.00;
$totalPaid = 0.00;
$enrolledCourses = 0;
try {
    $stmt = $pdo->prepare("SELECT id, student_number, program, year_level FROM students WHERE user_id = :uid");
    $stmt->execute(['uid' => $studentId]);
    $student = $stmt->fetch();

    if ($student) {
        // Get ledger by student.id (numeric)
        $stmt = $pdo->prepare("
            SELECT id, academic_year, description, amount_due, amount_paid, status, created_at
            FROM student_ledgers
            WHERE student_id = :sid
            ORDER BY created_at DESC
        ");
        $stmt->execute(['sid' => $student['id']]);
        $ledger = $stmt->fetchAll();

        $totalDue = array_sum(array_column($ledger, 'amount_due'));
        $totalPaid = array_sum(array_column($ledger, 'amount_paid'));

        // Count enrolled courses - use student.id (integer)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM student_courses WHERE student_id = :sid AND status = 'enrolled'");
        $stmt->execute(['sid' => $student['id']]);
        $enrolledCourses = (int) $stmt->fetchColumn();
    }
} catch (PDOException $e) {
    // Keep defaults on error
}

$balance = $totalDue - $totalPaid;

$pageTitle = 'Student Overview';
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

        <?php if ($student): ?>
            <!-- Balance Summary -->
            <div class="student-balance-summary" style="margin: 24px auto; max-width: 800px;">
                <div class="summary-title">Account Balance (<?= htmlspecialchars(get_academic_year($pdo)) ?>)</div>
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
            <article class="metric-card"><span class="eyebrow">Enrolled courses</span><strong><?= $enrolledCourses > 0 ? $enrolledCourses : '—' ?></strong><small><?= $enrolledCourses > 0 ? 'active courses' : 'no courses enrolled' ?></small></article>
            <article class="metric-card"><span class="eyebrow">Outstanding balance</span><strong><?= $balance > 0 ? '₱' . number_format($balance, 2) : '—' ?></strong><small><?= $balance > 0 ? 'payment required' : 'no outstanding balance' ?></small></article>
            <article class="metric-card"><span class="eyebrow">GPA</span><strong>—</strong><small>data not available</small></article>
            <article class="metric-card accent-card"><span class="eyebrow">Attendance</span><strong>—</strong><small>data not available</small><div class="progress"><span style="width:0%"></span></div></article>
        </section>
        <section class="content-grid">
            <article class="panel panel-wide">
                <div class="panel-heading"><div><span class="eyebrow">Quick actions</span><h2>Student Services</h2></div></div>
                <div class="action-list">
                    <a href="/student/enrollment.php"><span class="action-icon blue">01</span><span><strong>Enrollment</strong><small>View enrollment process and steps</small></span><span class="arrow">↗</span></a>
                    <a href="/student/load.php"><span class="action-icon green">02</span><span><strong>Study Load</strong><small>View enrolled courses and schedule</small></span><span class="arrow">↗</span></a>
                    <a href="/student/grades.php"><span class="action-icon amber">03</span><span><strong>Grades</strong><small>View grades and academic standing</small></span><span class="arrow">↗</span></a>
                    <a href="/student/subjects.php"><span class="action-icon amber">04</span><span><strong>Subjects</strong><small>View plan of study</small></span><span class="arrow">↗</span></a>
                    <a href="/student/statement.php"><span class="action-icon amber">05</span><span><strong>Statement of Account</strong><small>View balance and payment history</small></span><span class="arrow">↗</span></a>
                    <a href="/student/policies.php"><span class="action-icon amber">06</span><span><strong>Academic Policies</strong><small>View academic handbook</small></span><span class="arrow">↗</span></a>
                    <a href="/student/faq.php"><span class="action-icon amber">07</span><span><strong>FAQ</strong><small>Frequently asked questions</small></span><span class="arrow">↗</span></a>
                </div>
            </article>
        </section>
    </main>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>