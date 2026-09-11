<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_role('registrar');
require_once dirname(__DIR__) . '/db_connect.php';

$pdo = db_connect();

// Enrolled students count
$enrolledStudents = 0;
$totalStudents = 0;
$outstandingStudents = 0;
$totalOutstanding = 0.00;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE enrollment_status = 'enrolled'");
    $stmt->execute();
    $enrolledStudents = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM students");
    $stmt->execute();
    $totalStudents = (int) $stmt->fetchColumn();

    // Students with outstanding balance
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT sl.student_id), COALESCE(SUM(sl.amount_due - sl.amount_paid), 0)
        FROM student_ledgers sl
        WHERE sl.amount_due > sl.amount_paid
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_NUM);
    $outstandingStudents = (int) ($result[0] ?? 0);
    $totalOutstanding = (float) ($result[1] ?? 0);
} catch (PDOException $e) {
    // Keep defaults on error
}

$pageTitle = 'Registrar Overview';
$extraStyles = ['/assets/registrar.css'];
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="app-frame">
    <?php require dirname(__DIR__) . '/includes/sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div><span class="eyebrow">Registrar workspace</span><h1>Good morning, <?= htmlspecialchars(explode(' ', $_SESSION['user']['full_name'])[0], ENT_QUOTES, 'UTF-8') ?>.</h1></div>
            <div class="topbar-actions"><span class="year-pill">A.Y. 2026–2027</span><a class="avatar" href="/logout.php" title="Sign out"><?= strtoupper(substr((string) $_SESSION['user']['full_name'], 0, 1)) ?></a></div>
        </header>
        <section class="dashboard-grid">
            <article class="metric-card"><span class="eyebrow">Enrolled students</span><strong><?= number_format($enrolledStudents) ?></strong><small>Total: <?= number_format($totalStudents) ?></small></article>
            <article class="metric-card"><span class="eyebrow">Students with balance</span><strong><?= number_format($outstandingStudents) ?></strong><small>Of <?= number_format($totalStudents) ?> total</small></article>
            <article class="metric-card"><span class="eyebrow">Graduation candidates</span><strong>—</strong><small>Draft placeholder</small></article>
            <article class="metric-card accent-card"><span class="eyebrow">Total outstanding</span><strong>₱<?= number_format($totalOutstanding, 2) ?></strong><small>Across all students</small><div class="progress"><span style="width:0%"></span></div></article>
        </section>
        <section class="content-grid">
            <article class="panel panel-wide">
                <div class="panel-heading"><div><span class="eyebrow">Quick actions</span><h2>Records management</h2></div></div>
                <div class="action-list">
                    <a href="/registrar/students.php"><span class="action-icon blue">01</span><span><strong>Student masterlist</strong><small>Search and manage records</small></span><span class="arrow">↗</span></a>
                    <a href="/registrar/sections.php"><span class="action-icon green">02</span><span><strong>Sectioning</strong><small>Assign students to sections</small></span><span class="arrow">↗</span></a>
                    <a href="/registrar/subjects.php"><span class="action-icon amber">03</span><span><strong>Subject allocation</strong><small>Manage course offerings</small></span><span class="arrow">↗</span></a>
                    <a href="/registrar/payments.php"><span class="action-icon blue">04</span><span><strong>Payments / Assessment</strong><small>View balances and ledgers</small></span><span class="arrow">↗</span></a>
                </div>
            </article>
            <article class="panel">
                <div class="panel-heading"><div><span class="eyebrow">Recent activity</span><h2>Record changes</h2></div></div>
                <ul class="activity-list">
                    <li><span class="activity-dot green"></span><span><strong>Section assigned</strong><small>BSCS 2A · 30 min ago</small></span></li>
                    <li><span class="activity-dot blue"></span><span><strong>Record updated</strong><small>J. Dela Cruz · 2 hrs ago</small></span></li>
                    <li><span class="activity-dot amber"></span><span><strong>Graduation audit</strong><small>Batch 2024 · 1 day ago</small></span></li>
                </ul>
            </article>
        </section>
    </main>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>