<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_role('admin');
require_once dirname(__DIR__) . '/db_connect.php';

$pdo = db_connect();

$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = '';
$params = [];

if ($search !== '') {
    $where = 'WHERE s.student_number ILIKE :search OR u.full_name ILIKE :search';
    $params['search'] = "%{$search}%";
}

// Total count
$total = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM students s JOIN users u ON s.user_id = u.id {$where}");
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();
} catch (PDOException $e) {
    $total = 0;
}
$totalPages = max(1, (int) ceil($total / $perPage));
if ($page > $totalPages) $page = $totalPages;

// Fetch students
$students = [];
try {
    $stmt = $pdo->prepare("
        SELECT s.*, u.full_name, u.email, u.school_email, u.level_type, u.active_this_year
        FROM students s
        JOIN users u ON s.user_id = u.id
        {$where}
        ORDER BY s.student_number
        LIMIT :limit OFFSET :offset
    ");
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $students = $stmt->fetchAll();
} catch (PDOException $e) {
    // Empty on error
}

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

$pageTitle = 'Student Masterlist';
$extraStyles = ['/assets/admin.css'];
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="app-frame">
    <?php require dirname(__DIR__) . '/includes/sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div><span class="eyebrow">Administrator workspace</span><h1>Welcome, <?= htmlspecialchars(explode(' ', $_SESSION['user']['full_name'])[0], ENT_QUOTES, 'UTF-8') ?>.</h1></div>
            <div class="topbar-actions"><span class="year-pill">A.Y. 2026–2027</span><a class="avatar" href="#" onclick="return openLogoutModal()" title="Sign out"><?= strtoupper(substr((string) $_SESSION['user']['full_name'], 0, 1)) ?></a></div>
        </header>
        <section class="content-grid">
            <article class="panel panel-wide">
                <div class="panel-heading"><div><span class="eyebrow">Student Records</span><h2>Student Masterlist</h2></div></div>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <!-- Create Student Form -->
                <div class="user-form">
                    <h3 style="margin: 0 0 16px; font-size: 14px; font-weight: 700; color: var(--navy);">Create Student Record</h3>
                    <form method="post" action="/admin/process_student.php" class="user-form-inner">
                        <input type="hidden" name="action" value="create">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                        <div class="form-grid">
                            <div class="form-field">
                                <label for="student_number">Student Number *</label>
                                <input type="text" id="student_number" name="student_number" required placeholder="e.g. 2026-0001" style="height: 40px;">
                            </div>
                            <div class="form-field">
                                <label for="email">User Email *</label>
                                <input type="email" id="email" name="email" required placeholder="student@sma.edu.ph" style="height: 40px;">
                            </div>
                            <div class="form-field">
                                <label for="full_name">Full Name *</label>
                                <input type="text" id="full_name" name="full_name" required placeholder="Juan Dela Cruz" style="height: 40px;">
                            </div>
                            <div class="form-field">
                                <label for="program">Program *</label>
                                <input type="text" id="program" name="program" required placeholder="BS Information Technology" style="height: 40px;">
                            </div>
                            <div class="form-field">
                                <label for="year_level">Year Level *</label>
                                <select id="year_level" name="year_level" required style="height: 40px;">
                                    <option value="">-- Select Year Level --</option>
                                    <option value="Grade 7">Grade 7</option>
                                    <option value="Grade 8">Grade 8</option>
                                    <option value="Grade 9">Grade 9</option>
                                    <option value="Grade 10">Grade 10</option>
                                    <option value="Grade 11">Grade 11</option>
                                    <option value="Grade 12">Grade 12</option>
                                    <option value="1st Year">1st Year</option>
                                    <option value="2nd Year">2nd Year</option>
                                    <option value="3rd Year">3rd Year</option>
                                    <option value="4th Year">4th Year</option>
                                    <option value="NC I">NC I</option>
                                    <option value="NC II">NC II</option>
                                    <option value="NC III">NC III</option>
                                </select>
                            </div>
                            <div class="form-field">
                                <label for="enrollment_status">Enrollment Status *</label>
                                <select id="enrollment_status" name="enrollment_status" required style="height: 40px;">
                                    <option value="pending">Pending</option>
                                    <option value="verified">Verified</option>
                                    <option value="enrolled">Enrolled</option>
                                </select>
                            </div>
                            <div class="form-field">
                                <label for="school_email">School Email</label>
                                <input type="email" id="school_email" name="school_email" placeholder="student@sma.edu.ph" style="height: 40px;">
                            </div>
                            <div class="form-field">
                                <label for="level_type">Level Type</label>
                                <select id="level_type" name="level_type" style="height: 40px;">
                                    <option value="">-- Select Level Type --</option>
                                    <option value="BED">BED</option>
                                    <option value="College">College</option>
                                    <option value="TESDA">TESDA</option>
                                </select>
                            </div>
                            <div class="form-field" style="align-items: flex-end;">
                                <label for="active_this_year">Active This Year</label>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <input type="checkbox" id="active_this_year" name="active_this_year" value="1" checked>
                                    <label for="active_this_year" style="margin: 0; font-weight: 500; font-size: 13px;">Active this academic year</label>
                                </div>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn-create">Create Student</button>
                        </div>
                    </form>
                </div>

                <!-- Students List -->
                <div class="search-bar" style="margin-top: 24px;">
                    <form method="get" action="/admin/students.php" class="form-field" style="flex: 1; min-width: 250px;">
                        <label for="search" class="eyebrow">Search</label>
                        <input type="search" id="search" name="search" placeholder="Student number or name..." value="<?= htmlspecialchars($search) ?>" style="height: 40px;">
                    </form>
                    <span style="color: var(--muted); font-size: 13px;"><?= number_format($total) ?> student<?= $total !== 1 ? 's' : '' ?></span>
                </div>

                <?php if (empty($students)): ?>
                    <div class="empty-state">
                        <p>No students found<?= $search ? ' matching "' . htmlspecialchars($search) . '"' : '' ?>.</p>
                    </div>
                <?php else: ?>
                    <div class="table-wrapper" style="overflow-x: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Student Number</th>
                                    <th>Name</th>
                                    <th>Program</th>
                                    <th>Level Type</th>
                                    <th>Year Level</th>
                                    <th>Enrollment Status</th>
                                    <th>School Email</th>
                                    <th>Active This Year</th>
                                    <th style="text-align: center;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $s): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($s['student_number']) ?></strong></td>
                                        <td><?= htmlspecialchars($s['full_name']) ?></td>
                                        <td><?= htmlspecialchars($s['program']) ?></td>
                                        <td><?= htmlspecialchars($s['level_type'] ?? '—') ?></td>
                                        <td><?= htmlspecialchars($s['year_level']) ?></td>
                                        <td>
                                            <span class="status-badge status-<?= $s['enrollment_status'] === 'enrolled' ? 'paid' : ($s['enrollment_status'] === 'pending' ? 'partial' : 'unpaid') ?>" style="font-size: 11px; text-transform: capitalize;">
                                                <?= htmlspecialchars(ucfirst($s['enrollment_status'])) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($s['school_email'] ?? '—') ?></td>
                                        <td>
                                            <span class="status-badge status-<?= $s['active_this_year'] ? 'paid' : 'unpaid' ?>" style="font-size: 11px; text-transform: capitalize;">
                                                <?= $s['active_this_year'] ? 'Yes' : 'No' ?>
                                            </span>
                                        </td>
                                        <td style="text-align: center;">
                                            <div class="action-buttons">
                                                <button type="button" class="btn-edit" onclick="openEditModal(<?= (int) $s['id'] ?>, '<?= htmlspecialchars($s['student_number'], ENT_QUOTES) ?>', '<?= htmlspecialchars($s['full_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($s['program'], ENT_QUOTES) ?>', '<?= htmlspecialchars($s['year_level'], ENT_QUOTES) ?>', '<?= htmlspecialchars($s['enrollment_status'], ENT_QUOTES) ?>', '<?= htmlspecialchars($s['school_email'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($s['level_type'] ?? '', ENT_QUOTES) ?>', '<?= $s['active_this_year'] ? '1' : '0' ?>')" style="padding: 4px 10px; font-size: 11px;">Edit</button>
                                                <form method="post" action="/admin/process_student.php" style="display: inline;">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                                    <button type="submit" class="btn-suspend" onclick="return confirm('Are you sure you want to delete this student record? This action cannot be undone.');" style="padding: 4px 10px; font-size: 11px;">Delete</button>
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
                                <a href="/admin/students.php?page=<?= $page - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>" class="button button-secondary" style="font-size: 12px;">← Previous</a>
                            <?php endif; ?>
                            <span style="display: flex; align-items: center; color: var(--muted);">Page <?= $page ?> of <?= $totalPages ?></span>
                            <?php if ($page < $totalPages): ?>
                                <a href="/admin/students.php?page=<?= $page + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>" class="button button-secondary" style="font-size: 12px;">Next →</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </article>
        </section>
    </main>
</div>

<!-- Edit Student Modal -->
<div id="editModal" class="modal-backdrop" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div class="recovery-modal" style="background: #fff; border-radius: var(--radius); padding: 24px; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto;">
        <button type="button" class="modal-close" onclick="closeEditModal()" style="position: absolute; top: 12px; right: 12px; background: none; border: none; font-size: 24px; cursor: pointer; color: var(--muted);">&times;</button>
        <h3 style="margin: 0 0 16px; font-size: 16px; font-weight: 700; color: var(--navy);">Edit Student</h3>
        <form method="post" action="/admin/process_student.php" id="editForm">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-grid" style="display: grid; grid-template-columns: 1fr; gap: 14px;">
                <div class="form-field">
                    <label for="edit_student_number">Student Number *</label>
                    <input type="text" id="edit_student_number" name="student_number" required style="height: 40px;">
                </div>
                <div class="form-field">
                    <label for="edit_full_name">Full Name *</label>
                    <input type="text" id="edit_full_name" name="full_name" required style="height: 40px;">
                </div>
                <div class="form-field">
                    <label for="edit_program">Program *</label>
                    <input type="text" id="edit_program" name="program" required style="height: 40px;">
                </div>
                <div class="form-field">
                    <label for="edit_year_level">Year Level *</label>
                    <select id="edit_year_level" name="year_level" required style="height: 40px;">
                        <option value="Grade 7">Grade 7</option>
                        <option value="Grade 8">Grade 8</option>
                        <option value="Grade 9">Grade 9</option>
                        <option value="Grade 10">Grade 10</option>
                        <option value="Grade 11">Grade 11</option>
                        <option value="Grade 12">Grade 12</option>
                        <option value="1st Year">1st Year</option>
                        <option value="2nd Year">2nd Year</option>
                        <option value="3rd Year">3rd Year</option>
                        <option value="4th Year">4th Year</option>
                        <option value="NC I">NC I</option>
                        <option value="NC II">NC II</option>
                        <option value="NC III">NC III</option>
                    </select>
                </div>
                <div class="form-field">
                    <label for="edit_enrollment_status">Enrollment Status *</label>
                    <select id="edit_enrollment_status" name="enrollment_status" required style="height: 40px;">
                        <option value="pending">Pending</option>
                        <option value="verified">Verified</option>
                        <option value="enrolled">Enrolled</option>
                    </select>
                </div>
                <div class="form-field">
                    <label for="edit_school_email">School Email</label>
                    <input type="email" id="edit_school_email" name="school_email" placeholder="student@sma.edu.ph" style="height: 40px;">
                </div>
                <div class="form-field">
                    <label for="edit_level_type">Level Type</label>
                    <select id="edit_level_type" name="level_type" style="height: 40px;">
                        <option value="">-- Select Level Type --</option>
                        <option value="BED">BED</option>
                        <option value="College">College</option>
                        <option value="TESDA">TESDA</option>
                    </select>
                </div>
                <div class="form-field" style="align-items: flex-end;">
                    <label for="edit_active_this_year">Active This Year</label>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <input type="checkbox" id="edit_active_this_year" name="active_this_year" value="1">
                        <label for="edit_active_this_year" style="margin: 0; font-weight: 500; font-size: 13px;">Active this academic year</label>
                    </div>
                </div>
            </div>
            <div class="form-actions" style="margin-top: 16px; display: flex; gap: 12px; justify-content: flex-end;">
                <button type="submit" class="btn-save">Save Changes</button>
                <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(id, studentNumber, fullName, program, yearLevel, enrollmentStatus, schoolEmail, levelType, activeThisYear) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_student_number').value = studentNumber;
    document.getElementById('edit_full_name').value = fullName;
    document.getElementById('edit_program').value = program;
    document.getElementById('edit_year_level').value = yearLevel;
    document.getElementById('edit_enrollment_status').value = enrollmentStatus;
    document.getElementById('edit_school_email').value = schoolEmail || '';
    document.getElementById('edit_level_type').value = levelType || '';
    document.getElementById('edit_active_this_year').checked = activeThisYear === '1';
    document.getElementById('editModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});
</script>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>