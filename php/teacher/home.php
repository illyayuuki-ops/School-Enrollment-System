<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_role('teacher');
require_once dirname(__DIR__) . '/db_connect.php';
require_once dirname(__DIR__) . '/includes/settings.php';

$pdo = db_connect();
$teacherId = (int) ($_SESSION['user']['id'] ?? 0);

// Get settings from app_settings
$academicYear = get_academic_year($pdo);
$semester = get_default_semester($pdo);
$schoolName = get_school_name($pdo);

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

// Teacher attendance stats for current month
$attendanceStats = [
    'total' => 0,
    'present' => 0,
    'absent' => 0,
    'late' => 0,
    'leave' => 0,
    'rate' => 0,
];
try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
            SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late,
            SUM(CASE WHEN status = 'leave' THEN 1 ELSE 0 END) as leave
        FROM teacher_attendance
        WHERE teacher_id = :tid
        AND work_date >= DATE_TRUNC('month', CURRENT_DATE)
        AND work_date <= DATE_TRUNC('month', CURRENT_DATE) + INTERVAL '1 month' - INTERVAL '1 day'
    ");
    $stmt->execute(['tid' => $teacherId]);
    $attendanceStats = $stmt->fetch(PDO::FETCH_ASSOC);
    if (($attendanceStats['total'] ?? 0) > 0) {
        $attendanceStats['rate'] = round(($attendanceStats['present'] / $attendanceStats['total']) * 100, 1);
    }
} catch (PDOException $e) {
    // Keep defaults
}

// Recent attendance records
$recentAttendance = [];
try {
    $stmt = $pdo->prepare("
        SELECT work_date, status, remarks, created_at
        FROM teacher_attendance
        WHERE teacher_id = :tid
        ORDER BY work_date DESC
        LIMIT 5
    ");
    $stmt->execute(['tid' => $teacherId]);
    $recentAttendance = $stmt->fetchAll();
} catch (PDOException $e) {
    // Keep defaults
}

// Adviser section check - get actual advisees
$adviserSection = null;
$advisees = [];
try {
    $stmt = $pdo->prepare("
        SELECT adviser_section FROM classes_and_schedules 
        WHERE teacher_id = :tid AND adviser_section IS NOT NULL
        LIMIT 1
    ");
    $stmt->execute(['tid' => $teacherId]);
    $adviserSection = $stmt->fetchColumn();
    
    // If adviser, get actual advisees
    if ($adviserSection) {
        $stmt = $pdo->prepare("
            SELECT s.student_number, u.full_name
            FROM students s
            JOIN users u ON s.user_id = u.id
            WHERE s.year_level = (SELECT year_level FROM classes_and_schedules WHERE adviser_section = :as LIMIT 1)
            AND s.section = :as
            ORDER BY u.full_name
        ");
        $stmt->execute(['as' => $adviserSection]);
        $advisees = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    // Empty on error
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
            <div class="topbar-actions"><span class="year-pill"><?= htmlspecialchars($academicYear) ?></span><a class="avatar" href="#" onclick="return openLogoutModal()" title="Sign out"><?= strtoupper(substr((string) $_SESSION['user']['full_name'], 0, 1)) ?></a></div>
        </header>
        <section class="dashboard-grid">
            <article class="metric-card"><span class="eyebrow">Active classes</span><strong><?= $activeClasses ?></strong></article>
            <article class="metric-card"><span class="eyebrow">Total students</span><strong><?= $totalStudents ?></strong></article>
            <article class="metric-card"><span class="eyebrow">Pending grades</span><strong><?= $pendingGrades ?? '—' ?></strong></article>
            <article class="metric-card accent-card"><span class="eyebrow">Completion rate</span><strong><?= $completionRate ?? '—' ?></strong></article>
        </section>
        <section class="content-grid">
            <article class="panel panel-wide">
                <div class="panel-heading"><div><span class="eyebrow">Quick actions</span><h2>Teaching Tools</h2></div></div>
                <div class="action-list">
                    <a href="/teacher/classes.php"><span class="action-icon blue">01</span><span><strong>Active Classes</strong><small>Manage rosters and schedules</small></span><span class="arrow">↗</span></a>
                    <a href="/teacher/grades.php"><span class="action-icon green">02</span><span><strong>Grade Encoding</strong><small>Enter and submit grades</small></span><span class="arrow">↗</span></a>
                    <a href="/teacher/schedule.php"><span class="action-icon amber">03</span><span><strong>Schedule Matrix</strong><small>View weekly timetable</small></span><span class="arrow">↗</span></a>
                    <a href="/teacher/attendance.php"><span class="action-icon amber">04</span><span><strong>My Attendance</strong><small>View my attendance records</small></span><span class="arrow">↗</span></a>
                </div>
            </article>
            <article class="panel">
                <div class="panel-heading"><div><span class="eyebrow">My Attendance (This Month)</span><h2>Attendance Summary</h2></div></div>
                <div class="balance-cards" style="gap: 12px;">
                    <div class="balance-card">
                        <div class="card-label">Present</div>
                        <div class="card-value positive"><?= number_format($attendanceStats['present'] ?? 0) ?></div>
                    </div>
                    <div class="balance-card">
                        <div class="card-label">Absent</div>
                        <div class="card-value negative"><?= number_format($attendanceStats['absent'] ?? 0) ?></div>
                    </div>
                    <div class="balance-card">
                        <div class="card-label">Late</div>
                        <div class="card-value" style="color: #f59e0b;"><?= number_format($attendanceStats['late'] ?? 0) ?></div>
                    </div>
                    <div class="balance-card">
                        <div class="card-label">On Leave</div>
                        <div class="card-value" style="color: #6366f1;"><?= number_format($attendanceStats['leave'] ?? 0) ?></div>
                    </div>
                    <div class="balance-card">
                        <div class="card-label">Rate</div>
                        <div class="card-value positive"><?= number_format($attendanceStats['rate'] ?? 0, 1) ?>%</div>
                    </div>
                </div>
                <?php if (!empty($recentAttendance)): ?>
                    <div style="margin-top: 16px;">
                        <h4 style="margin: 0 0 12px; font-size: 13px; font-weight: 700; color: var(--navy);">Recent Records</h4>
                        <ul class="activity-list">
                            <?php foreach ($recentAttendance as $ra): ?>
                                <li>
                                    <span class="activity-dot" style="background: <?= $ra['status'] === 'present' ? 'var(--green)' : ($ra['status'] === 'absent' ? '#dc2626' : ($ra['status'] === 'late' ? '#f59e0b' : '#6366f1')) ?>;"></span>
                                    <span><strong><?= htmlspecialchars(ucfirst($ra['status'])) ?></strong><small><?= date('M d, Y', strtotime($ra['work_date'])) ?> <?= $ra['remarks'] ? '— ' . htmlspecialchars($ra['remarks']) : '' ?></small></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php else: ?>
                    <div class="empty-state" style="padding: 24px;">
                        <p>No attendance records this month.</p>
                    </div>
                <?php endif; ?>
            </article>
            <?php if ($adviserSection): ?>
            <article class="panel">
                <div class="panel-heading"><div><span class="eyebrow">Adviser</span><h2>My Advisees</h2></div></div>
                <div style="padding: 16px;">
                    <p>You are the adviser for section <strong><?= htmlspecialchars($adviserSection) ?></strong>.</p>
                    <?php if (!empty($advisees)): ?>
                        <div style="margin-top: 12px;">
                            <h4 style="margin: 0 0 8px; font-size: 13px; font-weight: 700; color: var(--navy);">Advisees</h4>
                            <ul style="margin: 0; padding-left: 20px;">
                                <?php foreach ($advisees as $advisee): ?>
                                    <li style="margin-bottom: 4px;">
                                        <strong><?= htmlspecialchars($advisee['student_number']) ?></strong> — <?= htmlspecialchars($advisee['full_name']) ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php else: ?>
                        <p style="margin-top: 8px; color: var(--muted); font-size: 13px;">No advisees assigned yet.</p>
                    <?php endif; ?>
                </div>
            </article>
            <?php endif; ?>
        </section>
    </main>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>