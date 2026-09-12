<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_role('teacher');
require_once dirname(__DIR__) . '/db_connect.php';

$pdo = db_connect();
$teacherId = (int) ($_SESSION['user']['id'] ?? 0);

// Active classes count
$activeClasses = 0;
$totalStudents = 0;
try {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM classes_and_schedules WHERE teacher_id = :tid');
    $stmt->execute(['tid' => $teacherId]);
    $activeClasses = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COALESCE(SUM(enrolled_count), 0) FROM classes_and_schedules WHERE teacher_id = :tid');
    $stmt->execute(['tid' => $teacherId]);
    $totalStudents = (int) $stmt->fetchColumn();
} catch (PDOException $e) {
    // Keep defaults on error
}

$pageTitle = 'Faculty Overview';
$extraStyles = ['/assets/teacher.css'];
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="app-frame">
    <?php require dirname(__DIR__) . '/includes/sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div><span class="eyebrow">Faculty workspace</span><h1>Welcome, <?= htmlspecialchars(explode(' ', $_SESSION['user']['full_name'])[0], ENT_QUOTES, 'UTF-8') ?>.</h1></div>
            <div class="topbar-actions"><span class="year-pill">A.Y. 2026–2027</span><a class="avatar" href="#" onclick="return openLogoutModal()" title="Sign out"><?= strtoupper(substr((string) $_SESSION['user']['full_name'], 0, 1)) ?></a></div>
        </header>
        <section class="dashboard-grid">
            <article class="metric-card"><span class="eyebrow">Active classes</span><strong><?= $activeClasses ?></strong><small><b class="trend-up">+0</b> this term</small></article>
            <article class="metric-card"><span class="eyebrow">Total students</span><strong><?= $totalStudents ?></strong><small><b class="trend-up">+0</b> vs last term</small></article>
            <article class="metric-card"><span class="eyebrow">Pending grades</span><strong>—</strong><small>Draft placeholder</small></article>
            <article class="metric-card accent-card"><span class="eyebrow">Completion rate</span><strong>—</strong><small>data not available</small><div class="progress"><span style="width:0%"></span></div></article>
        </section>
        <section class="content-grid">
            <article class="panel panel-wide">
                <div class="panel-heading"><div><span class="eyebrow">Quick Actions</span><h2>Teaching Tools</h2></div></div>
                <div class="action-list">
                    <a href="/teacher/classes.php"><span class="action-icon blue">01</span><span><strong>Active Classes</strong><small>Manage rosters and schedules</small></span><span class="arrow">↗</span></a>
                    <a href="/teacher/grades.php"><span class="action-icon green">02</span><span><strong>Grade Encoding</strong><small>Enter and submit grades</small></span><span class="arrow">↗</span></a>
                    <a href="/teacher/schedule.php"><span class="action-icon amber">03</span><span><strong>Schedule Matrix</strong><small>View weekly timetable</small></span><span class="arrow">↗</span></a>
                </div>
            </article>
            <article class="panel">
                <div class="panel-heading"><div><span class="eyebrow">Recent Activity</span><h2>Class Updates</h2></div></div>
                <ul class="activity-list">
                    <li><span class="activity-dot green"></span><span><strong>Grades submitted</strong><small>CS101 Midterm · 2 hrs ago</small></span></li>
                    <li><span class="activity-dot blue"></span><span><strong>Attendance recorded</strong><small>MATH201 Lec · 4 hrs ago</small></span></li>
                    <li><span class="activity-dot amber"></span><span><strong>Assignment posted</strong><small>PHYS101 HW3 · 1 day ago</small></span></li>
                </ul>
            </article>
        </section>
    </main>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>