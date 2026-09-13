<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_role('teacher');
require_once dirname(__DIR__) . '/db_connect.php';
require_once dirname(__DIR__) . '/includes/settings.php';

$pdo = db_connect();
$teacherId = (int) ($_SESSION['user']['id'] ?? 0);

$academicYear = get_academic_year($pdo);

// Get teacher's classes for subject filter
$teacherClasses = [];
try {
    $stmt = $pdo->prepare('SELECT subject_code, subject_name FROM classes_and_schedules WHERE teacher_id = :tid ORDER BY subject_code');
    $stmt->execute(['tid' => $teacherId]);
    $teacherClasses = $stmt->fetchAll();
} catch (PDOException $e) {
    // Empty on error
}

// Filters
$subjectFilter = $_GET['subject'] ?? '';
$monthFilter = $_GET['month'] ?? date('Y-m');

// Monthly attendance stats
$attendanceStats = [
    'total' => 0,
    'present' => 0,
    'absent' => 0,
    'late' => 0,
    'leave' => 0,
    'rate' => 0,
];

$monthStart = date('Y-m-01', strtotime($monthFilter . '-01'));
$monthEnd = date('Y-m-t', strtotime($monthFilter . '-01'));

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
        AND work_date >= :start_date
        AND work_date <= :end_date
    ");
    $stmt->execute([
        'tid' => $teacherId,
        'start_date' => $monthStart,
        'end_date' => $monthEnd,
    ]);
    $attendanceStats = $stmt->fetch(PDO::FETCH_ASSOC);
    if (($attendanceStats['total'] ?? 0) > 0) {
        $attendanceStats['rate'] = round(($attendanceStats['present'] / $attendanceStats['total']) * 100, 1);
    }
} catch (PDOException $e) {
    // Keep defaults
}

// Fetch attendance records
$attendanceRecords = [];
try {
    $where = "WHERE ta.teacher_id = :tid AND ta.work_date >= :start_date AND ta.work_date <= :end_date";
    $params = [
        'tid' => $teacherId,
        'start_date' => $monthStart,
        'end_date' => $monthEnd,
    ];
    
    if ($subjectFilter) {
        // We could join with classes to filter by subject, but teacher_attendance doesn't have subject directly
        // For now, we'll just filter by teacher and date
    }
    
    $stmt = $pdo->prepare("
        SELECT ta.work_date, ta.status, ta.remarks, ta.created_at
        FROM teacher_attendance ta
        WHERE ta.teacher_id = :tid
        AND ta.work_date >= :start_date
        AND ta.work_date <= :end_date
        ORDER BY ta.work_date DESC, ta.created_at DESC
    ");
    $stmt->execute([
        'tid' => $teacherId,
        'start_date' => $monthStart,
        'end_date' => $monthEnd,
    ]);
    $attendanceRecords = $stmt->fetchAll();
} catch (PDOException $e) {
    // Empty on error
}

$pageTitle = 'My Attendance';
$extraStyles = ['/assets/teacher.css'];
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="app-frame">
    <?php require dirname(__DIR__) . '/includes/sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div><span class="eyebrow">Faculty workspace</span><h1>Welcome, <?= htmlspecialchars(explode(' ', $_SESSION['user']['full_name'])[0], ENT_QUOTES, 'UTF-8') ?>.</h1></div>
            <div class="topbar-actions"><span class="year-pill"><?= htmlspecialchars(get_academic_year($pdo)) ?></span><a class="avatar" href="#" onclick="return openLogoutModal()" title="Sign out"><?= strtoupper(substr((string) $_SESSION['user']['full_name'], 0, 1)) ?></a></div>
        </header>
        <section class="content-grid">
            <article class="panel panel-wide">
                <div class="panel-heading"><div><span class="eyebrow">Attendance</span><h2>My Attendance</h2></div></div>
                
                <!-- Month Filter -->
                <div class="search-bar" style="margin-bottom: 24px;">
                    <form method="get" action="/teacher/attendance.php" style="display: flex; align-items: end; gap: 8px; flex-wrap: wrap;">
                        <div class="form-field" style="min-width: 160px;">
                            <label for="month" class="eyebrow">Month</label>
                            <input type="month" id="month" name="month" value="<?= htmlspecialchars($monthFilter) ?>" style="height: 40px;">
                        </div>
                        <button class="button button-primary" type="submit" style="height: 40px;">Filter</button>
                    </form>
                    <span style="color: var(--muted); font-size: 13px;">Academic Year: <?= htmlspecialchars(get_academic_year($pdo)) ?></span>
                </div>

                <!-- Summary Cards -->
                <div class="balance-cards" style="margin-bottom: 24px;">
                    <div class="balance-card">
                        <div class="card-label">Total Records</div>
                        <div class="card-value"><?= number_format($attendanceStats['total'] ?? 0) ?></div>
                    </div>
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

                <!-- Attendance Records -->
                <?php if (empty($attendanceRecords)): ?>
                    <div class="empty-state">
                        <p>No attendance records for <?= date('F Y', strtotime($monthFilter . '-01')) ?>.</p>
                    </div>
                <?php else: ?>
                    <div class="table-wrapper" style="overflow-x: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Remarks</th>
                                    <th>Recorded At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attendanceRecords as $record): ?>
                                    <tr>
                                        <td><?= date('M d, Y', strtotime($record['work_date'])) ?></td>
                                        <td>
                                            <span class="status-badge status-<?= htmlspecialchars($record['status']) ?>" style="text-transform: capitalize;">
                                                <?= htmlspecialchars(ucfirst($record['status'])) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($record['remarks'] ?? '—') ?></td>
                                        <td><?= date('M d, Y H:i', strtotime($record['created_at'])) ?></td>
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