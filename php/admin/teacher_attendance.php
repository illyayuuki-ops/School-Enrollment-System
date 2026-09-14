<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_role('admin');
require_once dirname(__DIR__) . '/db_connect.php';
require_once dirname(__DIR__) . '/includes/settings.php';

$pdo = db_connect();

$search = trim($_GET['search'] ?? '');
$teacherFilter = $_GET['teacher_id'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$periodFilter = $_GET['period'] ?? 'month';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Get all teachers for dropdown
$teachers = [];
try {
    $stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE role = 'teacher' ORDER BY full_name");
    $stmt->execute();
    $teachers = $stmt->fetchAll();
} catch (PDOException $e) {
    // Empty on error
}

// Determine date range based on period filter
$dateCondition = '';
$dateParams = [];
$now = new DateTime();
switch ($periodFilter) {
    case 'week':
        $startDate = (clone $now)->modify('monday this week')->format('Y-m-d');
        $endDate = (clone $now)->modify('sunday this week')->format('Y-m-d');
        break;
    case 'month':
        $startDate = (clone $now)->modify('first day of this month')->format('Y-m-d');
        $endDate = (clone $now)->modify('last day of this month')->format('Y-m-d');
        break;
    case 'year':
        $startDate = (clone $now)->modify('first day of January this year')->format('Y-m-d');
        $endDate = (clone $now)->modify('last day of December this year')->format('Y-m-d');
        break;
    default:
        $startDate = (clone $now)->modify('first day of this month')->format('Y-m-d');
        $endDate = (clone $now)->modify('last day of this month')->format('Y-m-d');
}

$where = "WHERE ta.work_date BETWEEN :start_date AND :end_date";
$dateParams['start_date'] = $startDate;
$dateParams['end_date'] = $endDate;

if ($teacherFilter !== '') {
    $where .= ' AND ta.teacher_id = :teacher_id';
    $dateParams['teacher_id'] = $teacherFilter;
}

if ($statusFilter !== '') {
    $where .= ' AND ta.status = :status';
    $dateParams['status'] = $statusFilter;
}

// Total count
$total = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM teacher_attendance ta JOIN users u ON ta.teacher_id = u.id {$where}");
    $stmt->execute($dateParams);
    $total = (int) $stmt->fetchColumn();
} catch (PDOException $e) {
    $total = 0;
}
$totalPages = max(1, (int) ceil($total / $perPage));
if ($page > $totalPages) $page = $totalPages;

// Fetch attendance records
$attendance = [];
try {
    $stmt = $pdo->prepare("
        SELECT ta.*, u.full_name as teacher_name
        FROM teacher_attendance ta
        JOIN users u ON ta.teacher_id = u.id
        {$where}
        ORDER BY ta.work_date DESC, u.full_name
        LIMIT :limit OFFSET :offset
    ");
    foreach ($dateParams as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $attendance = $stmt->fetchAll();
} catch (PDOException $e) {
    // Empty on error
}

// Statistical data
$stats = [
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
        FROM teacher_attendance ta
        {$where}
    ");
    $stmt->execute($dateParams);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($stats['total'] > 0) {
        $stats['rate'] = round(($stats['present'] / $stats['total']) * 100, 1);
    }
} catch (PDOException $e) {
    // Keep defaults
}

// Per-teacher stats for the period
$teacherStats = [];
try {
    $stmt = $pdo->prepare("
        SELECT u.full_name, u.id,
            COUNT(*) as total,
            SUM(CASE WHEN ta.status = 'present' THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN ta.status = 'absent' THEN 1 ELSE 0 END) as absent,
            SUM(CASE WHEN ta.status = 'late' THEN 1 ELSE 0 END) as late,
            SUM(CASE WHEN ta.status = 'leave' THEN 1 ELSE 0 END) as leave
        FROM teacher_attendance ta
        JOIN users u ON ta.teacher_id = u.id
        {$where}
        GROUP BY u.id, u.full_name
        ORDER BY u.full_name
    ");
    $stmt->execute($dateParams);
    $teacherStats = $stmt->fetchAll();
} catch (PDOException $e) {
    // Empty on error
}

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

$pageTitle = 'Teacher Attendance';
$extraStyles = ['/assets/admin.css'];
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="app-frame">
    <?php require dirname(__DIR__) . '/includes/sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div><span class="eyebrow">Administrator workspace</span><h1>Welcome, <?= htmlspecialchars(explode(' ', $_SESSION['user']['full_name'])[0], ENT_QUOTES, 'UTF-8') ?>.</h1></div>
            <div class="topbar-actions"><span class="year-pill"><?= htmlspecialchars(get_academic_year($pdo)) ?></span><a class="avatar" href="#" onclick="return openLogoutModal()" title="Sign out"><?= strtoupper(substr((string) $_SESSION['user']['full_name'], 0, 1)) ?></a></div>
        </header>
        <section class="content-grid">
            <article class="panel panel-wide">
                <div class="panel-heading"><div><span class="eyebrow">Attendance</span><h2>Teacher Attendance Management</h2></div></div>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <!-- Record Attendance Form -->
                <div class="user-form" style="margin-bottom: 24px;">
                    <h3 style="margin: 0 0 16px; font-size: 14px; font-weight: 700; color: var(--navy);">Record Attendance</h3>
                    <form method="post" action="/admin/process_teacher_attendance.php" class="user-form-inner">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <div class="form-grid">
                            <div class="form-field">
                                <label for="teacher_id">Teacher *</label>
                                <select id="teacher_id" name="teacher_id" required style="height: 40px;">
                                    <option value="">-- Select Teacher --</option>
                                    <?php foreach ($teachers as $t): ?>
                                        <option value="<?= (int) $t['id'] ?>"><?= htmlspecialchars($t['full_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-field">
                                <label for="work_date">Date *</label>
                                <input type="date" id="work_date" name="work_date" required value="<?= date('Y-m-d') ?>" style="height: 40px;">
                            </div>
                            <div class="form-field">
                                <label for="status">Status *</label>
                                <select id="status" name="status" required style="height: 40px;">
                                    <option value="present">Present</option>
                                    <option value="absent">Absent</option>
                                    <option value="late">Late</option>
                                    <option value="leave">On Leave</option>
                                </select>
                            </div>
                            <div class="form-field">
                                <label for="remarks">Remarks</label>
                                <input type="text" id="remarks" name="remarks" placeholder="Optional remarks" style="height: 40px;">
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn-create">Record Attendance</button>
                        </div>
                    </form>
                </div>

                <!-- Summary Cards -->
                <div class="balance-cards" style="margin: 24px 0;">
                    <div class="balance-card">
                        <div class="card-label">Total Records</div>
                        <div class="card-value"><?= number_format($stats['total'] ?? 0) ?></div>
                    </div>
                    <div class="balance-card">
                        <div class="card-label">Present</div>
                        <div class="card-value positive"><?= number_format($stats['present'] ?? 0) ?></div>
                    </div>
                    <div class="balance-card">
                        <div class="card-label">Absent</div>
                        <div class="card-value negative"><?= number_format($stats['absent'] ?? 0) ?></div>
                    </div>
                    <div class="balance-card">
                        <div class="card-label">Late</div>
                        <div class="card-value" style="color: #f59e0b;"><?= number_format($stats['late'] ?? 0) ?></div>
                    </div>
                    <div class="balance-card">
                        <div class="card-label">On Leave</div>
                        <div class="card-value" style="color: #6366f1;"><?= number_format($stats['leave'] ?? 0) ?></div>
                    </div>
                    <div class="balance-card">
                        <div class="card-label">Attendance Rate</div>
                        <div class="card-value positive"><?= number_format($stats['rate'] ?? 0, 1) ?>%</div>
                    </div>
                </div>

                <!-- Teacher Statistics Table -->
                <div style="margin-top: 24px;">
                    <h3 style="margin: 0 0 16px; font-size: 14px; font-weight: 700; color: var(--navy);">Teacher Attendance Summary</h3>
                    <div class="table-wrapper" style="overflow-x: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Teacher</th>
                                    <th style="text-align: center;">Total</th>
                                    <th style="text-align: center;">Present</th>
                                    <th style="text-align: center;">Absent</th>
                                    <th style="text-align: center;">Late</th>
                                    <th style="text-align: center;">On Leave</th>
                                    <th style="text-align: center;">Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($teacherStats)): ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 40px; color: var(--muted);">No attendance records for the selected period.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($teacherStats as $ts): ?>
                                        <?php
                                        $total = (int) $ts['total'];
                                        $present = (int) $ts['present'];
                                        $rate = $total > 0 ? round(($present / $total) * 100, 1) : 0;
                                        ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($ts['full_name']) ?></strong></td>
                                            <td style="text-align: center;"><?= (int) $ts['total'] ?></td>
                                            <td style="text-align: center;"><span class="status-badge status-paid"><?= (int) $ts['present'] ?></span></td>
                                            <td style="text-align: center;"><span class="status-badge status-unpaid"><?= (int) $ts['absent'] ?></span></td>
                                            <td style="text-align: center;"><span class="status-badge status-partial"><?= (int) $ts['late'] ?></span></td>
                                            <td style="text-align: center;"><span class="status-badge" style="background: #ede9fe; color: #4f46e5;"><?= (int) $ts['leave'] ?></span></td>
                                            <td style="text-align: center;"><strong><?= number_format($rate, 1) ?>%</strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </article>
        </section>

        <!-- Attendance Records Section -->
        <section class="content-grid">
            <article class="panel panel-wide">
                <div class="panel-heading"><div><span class="eyebrow">Records</span><h2>Attendance Records</h2></div></div>

                <div class="search-bar">
                    <form method="get" action="/admin/teacher_attendance.php" class="form-field" style="flex: 1; min-width: 250px;">
                        <label for="search" class="eyebrow">Search Teacher</label>
                        <input type="search" id="search" name="search" placeholder="Teacher name..." style="height: 40px;">
                    </form>
                    <form method="get" action="/admin/teacher_attendance.php" style="display: flex; align-items: end; gap: 8px;">
                        <input type="hidden" name="period" value="<?= htmlspecialchars($periodFilter) ?>">
                        <div class="form-field" style="min-width: 160px;">
                            <label for="teacher_filter" class="eyebrow">Teacher</label>
                            <select id="teacher_filter" name="teacher_id" style="height: 40px;">
                                <option value="">All Teachers</option>
                                <?php foreach ($teachers as $t): ?>
                                    <option value="<?= (int) $t['id'] ?>" <?= $teacherFilter == $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['full_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-field" style="min-width: 140px;">
                            <label for="status_filter" class="eyebrow">Status</label>
                            <select id="status_filter" name="status" style="height: 40px;">
                                <option value="">All Status</option>
                                <option value="present" <?= $statusFilter === 'present' ? 'selected' : '' ?>>Present</option>
                                <option value="absent" <?= $statusFilter === 'absent' ? 'selected' : '' ?>>Absent</option>
                                <option value="late" <?= $statusFilter === 'late' ? 'selected' : '' ?>>Late</option>
                                <option value="leave" <?= $statusFilter === 'leave' ? 'selected' : '' ?>>On Leave</option>
                            </select>
                        </div>
                        <div class="form-field" style="min-width: 140px;">
                            <label for="period_filter" class="eyebrow">Period</label>
                            <select id="period_filter" name="period" style="height: 40px;">
                                <option value="week" <?= $periodFilter === 'week' ? 'selected' : '' ?>>This Week</option>
                                <option value="month" <?= $periodFilter === 'month' ? 'selected' : '' ?>>This Month</option>
                                <option value="year" <?= $periodFilter === 'year' ? 'selected' : '' ?>>This Year</option>
                            </select>
                        </div>
                        <button class="button button-primary" type="submit" style="height: 40px;">Filter</button>
                    </form>
                    <span style="color: var(--muted); font-size: 13px;"><?= number_format($total) ?> record<?= $total !== 1 ? 's' : '' ?></span>
                </div>

                <?php if (empty($attendance)): ?>
                    <div class="empty-state">
                        <p>No attendance records found<?= $search ? ' matching "' . htmlspecialchars($search) . '"' : '' ?><?= $teacherFilter ? ' for selected teacher' : '' ?><?= $statusFilter ? ' with status ' . ucfirst($statusFilter) : '' ?>.</p>
                    </div>
                <?php else: ?>
                    <div class="table-wrapper" style="overflow-x: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Teacher</th>
                                    <th>Status</th>
                                    <th>Remarks</th>
                                    <th>Recorded By</th>
                                    <th>Created</th>
                                    <th style="text-align: center;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attendance as $a): ?>
                                    <tr>
                                        <td><?= date('M d, Y', strtotime($a['work_date'])) ?></td>
                                        <td><?= htmlspecialchars($a['teacher_name']) ?></td>
                                        <td>
                                            <span class="status-badge status-<?= htmlspecialchars($a['status']) ?>" style="text-transform: capitalize;">
                                                <?= htmlspecialchars(ucfirst($a['status'])) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($a['remarks'] ?? '—') ?></td>
                                        <td><?= htmlspecialchars($a['recorded_by'] ?? '—') ?></td>
                                        <td><?= date('M d, Y H:i', strtotime($a['created_at'])) ?></td>
                                        <td style="text-align: center;">
                                            <div class="action-buttons">
                                                <button type="button" class="btn-edit" onclick="openEditAttendanceModal(<?= (int) $a['id'] ?>, '<?= htmlspecialchars($a['teacher_id'], ENT_QUOTES) ?>', '<?= htmlspecialchars($a['work_date'], ENT_QUOTES) ?>', '<?= htmlspecialchars($a['status'], ENT_QUOTES) ?>', '<?= htmlspecialchars($a['remarks'] ?? '', ENT_QUOTES) ?>')" style="padding: 4px 10px; font-size: 11px;">Edit</button>
                                                <form method="post" action="/admin/process_teacher_attendance.php" style="display: inline;" onsubmit="return confirm('Delete this attendance record?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                    <button type="submit" class="btn-suspend" style="padding: 4px 10px; font-size: 11px;">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($totalPages > 1): ?>
                        <div style="display: flex; justify-content: center; gap: 8px; margin-top: 16px;">
                            <?php if ($page > 1): ?>
                                <a href="/admin/teacher_attendance.php?page=<?= $page - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $teacherFilter ? '&teacher_id=' . urlencode($teacherFilter) : '' ?><?= $statusFilter ? '&status=' . urlencode($statusFilter) : '' ?><?= $periodFilter !== 'month' ? '&period=' . urlencode($periodFilter) : '' ?>" class="button button-secondary" style="font-size: 12px;">← Previous</a>
                            <?php endif; ?>
                            <span style="display: flex; align-items: center; color: var(--muted);">Page <?= $page ?> of <?= $totalPages ?></span>
                            <?php if ($page < $totalPages): ?>
                                <a href="/admin/teacher_attendance.php?page=<?= $page + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $teacherFilter ? '&teacher_id=' . urlencode($teacherFilter) : '' ?><?= $statusFilter ? '&status=' . urlencode($statusFilter) : '' ?><?= $periodFilter !== 'month' ? '&period=' . urlencode($periodFilter) : '' ?>" class="button button-secondary" style="font-size: 12px;">Next →</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </article>
        </section>
    </main>
</div>

<!-- Edit Attendance Modal -->
<div id="editAttendanceModal" class="modal-backdrop" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div class="recovery-modal" style="background: #fff; border-radius: var(--radius); padding: 24px; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto;">
        <button type="button" class="modal-close" onclick="closeEditAttendanceModal()" style="position: absolute; top: 12px; right: 12px; background: none; border: none; font-size: 24px; cursor: pointer; color: var(--muted);">&times;</button>
        <h3 style="margin: 0 0 16px; font-size: 16px; font-weight: 700; color: var(--navy);">Edit Attendance Record</h3>
        <form method="post" action="/admin/process_teacher_attendance.php" id="editAttendanceForm">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="id" id="edit_attendance_id">
            <div class="form-grid" style="display: grid; grid-template-columns: 1fr; gap: 14px;">
                <div class="form-field">
                    <label for="edit_teacher_id">Teacher *</label>
                    <select id="edit_teacher_id" name="teacher_id" required style="height: 40px;">
                        <?php foreach ($teachers as $t): ?>
                            <option value="<?= (int) $t['id'] ?>"><?= htmlspecialchars($t['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-field">
                    <label for="edit_work_date">Date *</label>
                    <input type="date" id="edit_work_date" name="work_date" required style="height: 40px;">
                </div>
                <div class="form-field">
                    <label for="edit_status">Status *</label>
                    <select id="edit_status" name="status" required style="height: 40px;">
                        <option value="present">Present</option>
                        <option value="absent">Absent</option>
                        <option value="late">Late</option>
                        <option value="leave">On Leave</option>
                    </select>
                </div>
                <div class="form-field">
                    <label for="edit_remarks">Remarks</label>
                    <input type="text" id="edit_remarks" name="remarks" placeholder="Optional remarks" style="height: 40px;">
                </div>
            </div>
            <div class="form-actions" style="margin-top: 16px; display: flex; gap: 12px; justify-content: flex-end;">
                <button type="submit" class="btn-save">Save Changes</button>
                <button type="button" class="btn-cancel" onclick="closeEditAttendanceModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditAttendanceModal(id, teacherId, workDate, status, remarks) {
    document.getElementById('edit_attendance_id').value = id;
    document.getElementById('edit_teacher_id').value = teacherId;
    document.getElementById('edit_work_date').value = workDate;
    document.getElementById('edit_status').value = status;
    document.getElementById('edit_remarks').value = remarks || '';
    document.getElementById('editAttendanceModal').style.display = 'flex';
}

function closeEditAttendanceModal() {
    document.getElementById('editAttendanceModal').style.display = 'none';
}

document.getElementById('editAttendanceModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditAttendanceModal();
});
</script>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>