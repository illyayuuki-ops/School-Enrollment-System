<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_role('registrar');
require_once dirname(__DIR__) . '/db_connect.php';
require_once dirname(__DIR__) . '/includes/settings.php';

$pdo = db_connect();

$search = trim($_GET['search'] ?? '');
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

$where = '';
$params = [];

if ($search !== '') {
    $where = 'WHERE c.subject_code ILIKE :search OR c.subject_name ILIKE :search';
    $params['search'] = "%{$search}%";
}

// Total count
$total = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM classes_and_schedules c {$where}");
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();
} catch (PDOException $e) {
    $total = 0;
}
$totalPages = max(1, (int) ceil($total / $perPage));
if ($page > $totalPages) $page = $totalPages;

// Fetch classes
$classes = [];
try {
    $stmt = $pdo->prepare("
        SELECT c.*, u.full_name as teacher_name
        FROM classes_and_schedules c
        LEFT JOIN users u ON c.teacher_id = u.id
        {$where}
        ORDER BY c.subject_code
        LIMIT :limit OFFSET :offset
    ");
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $classes = $stmt->fetchAll();
} catch (PDOException $e) {
    // Empty on error
}

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

$pageTitle = 'Subject Allocation';
$extraStyles = ['/assets/registrar.css'];
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="app-frame">
    <?php require dirname(__DIR__) . '/includes/sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div><span class="eyebrow">Registrar workspace</span><h1>Welcome, <?= htmlspecialchars(explode(' ', $_SESSION['user']['full_name'])[0], ENT_QUOTES, 'UTF-8') ?>.</h1></div>
            <div class="topbar-actions"><span class="year-pill"><?= htmlspecialchars(get_academic_year($pdo)) ?></span><a class="avatar" href="#" onclick="return openLogoutModal()" title="Sign out"><?= strtoupper(substr((string) $_SESSION['user']['full_name'], 0, 1)) ?></a></div>
        </header>
        <section class="content-grid">
            <article class="panel panel-wide">
                <div class="panel-heading"><div><span class="eyebrow">Records</span><h2>Subject Allocation</h2></div></div>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <!-- Create Subject Form -->
                <div class="user-form" style="margin-bottom: 24px;">
                    <h3 style="margin: 0 0 16px; font-size: 14px; font-weight: 700; color: var(--navy);">Add New Subject</h3>
                    <form method="post" action="/registrar/process_subject.php" class="user-form-inner">
                        <input type="hidden" name="action" value="create">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

                        <div class="form-grid">
                            <div class="form-field">
                                <label for="subject_code">Subject Code *</label>
                                <input type="text" id="subject_code" name="subject_code" required placeholder="e.g. CS101" style="height: 40px;">
                            </div>
                            <div class="form-field">
                                <label for="subject_name">Subject Name *</label>
                                <input type="text" id="subject_name" name="subject_name" required placeholder="e.g. Introduction to Computer Science" style="height: 40px;">
                            </div>
                            <div class="form-field">
                                <label for="teacher_id">Teacher</label>
                                <select id="teacher_id" name="teacher_id" style="height: 40px;">
                                    <option value="">-- Unassigned --</option>
                                    <?php foreach ($teachers as $t): ?>
                                        <option value="<?= (int) $t['id'] ?>"><?= htmlspecialchars($t['full_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-field">
                                <label for="schedule_grid">Schedule *</label>
                                <input type="text" id="schedule_grid" name="schedule_grid" required placeholder="e.g. Mon 08:00-10:00, Wed 13:00-15:00" style="height: 40px;">
                            </div>
                            <div class="form-field">
                                <label for="room">Room *</label>
                                <input type="text" id="room" name="room" required placeholder="e.g. Room 101" style="height: 40px;">
                            </div>
                            <div class="form-field">
                                <label for="capacity">Capacity *</label>
                                <input type="number" id="capacity" name="capacity" min="1" max="100" required value="40" style="height: 40px;">
                            </div>
                            <div class="form-field">
                                <label for="units">Units</label>
                                <input type="number" id="units" name="units" min="0" max="10" value="3" style="height: 40px;">
                            </div>
                            <div class="form-field">
                                <label for="adviser_section">Adviser Section</label>
                                <input type="text" id="adviser_section" name="adviser_section" placeholder="e.g. BSCS-2A" style="height: 40px;">
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn-create">Add Subject</button>
                        </div>
                    </form>
                </div>

                <!-- Search and Filter -->
                <div class="search-bar" style="margin-top: 24px;">
                    <form method="get" action="/registrar/subjects.php" class="form-field" style="flex: 1; min-width: 250px;">
                        <label for="search" class="eyebrow">Search</label>
                        <input type="search" id="search" name="search" placeholder="Subject code or name..." value="<?= htmlspecialchars($search) ?>" style="height: 40px;">
                    </form>
                    <span style="color: var(--muted); font-size: 13px;"><?= number_format($total) ?> subject<?= $total !== 1 ? 's' : '' ?></span>
                </div>

                <?php if (empty($classes)): ?>
                    <div class="empty-state">
                        <p>No subjects allocated yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-wrapper" style="overflow-x: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Subject Code</th>
                                    <th>Subject Name</th>
                                    <th>Teacher</th>
                                    <th>Schedule</th>
                                    <th>Room</th>
                                    <th style="text-align: center;">Units</th>
                                    <th style="text-align: right;">Enrolled / Capacity</th>
                                    <th style="text-align: center;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($classes as $c): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($c['subject_code']) ?></strong></td>
                                        <td><?= htmlspecialchars($c['subject_name']) ?></td>
                                        <td><?= htmlspecialchars($c['teacher_name'] ?? 'Unassigned') ?></td>
                                        <td style="font-family: monospace; font-size: 12px;"><?= htmlspecialchars($c['schedule_grid']) ?></td>
                                        <td><?= htmlspecialchars($c['room']) ?></td>
                                        <td style="text-align: center;"><?= $c['units'] !== null ? (int) $c['units'] : '—' ?></td>
                                        <td style="text-align: right;"><?= (int) $c['enrolled_count'] ?> / <?= (int) $c['capacity'] ?></td>
                                        <td style="text-align: center;">
                                            <div class="action-buttons">
                                                <button type="button" class="btn-edit" onclick="openEditModal(<?= (int) $c['id'] ?>, '<?= htmlspecialchars($c['subject_code'], ENT_QUOTES) ?>', '<?= htmlspecialchars($c['subject_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($c['schedule_grid'], ENT_QUOTES) ?>', '<?= htmlspecialchars($c['room'], ENT_QUOTES) ?>', <?= (int) $c['capacity'] ?>, <?= $c['units'] !== null ? (int) $c['units'] : 0 ?>, '<?= htmlspecialchars($c['adviser_section'] ?? '', ENT_QUOTES) ?>')" style="padding: 4px 10px; font-size: 11px;">Edit</button>
                                                <form method="post" action="/registrar/process_subject.php" style="display: inline;" onsubmit="return confirm('Delete this subject?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
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
                <?php endif; ?>
            </article>
        </section>
    </main>
</div>

<!-- Edit Subject Modal -->
<div id="editModal" class="modal-backdrop" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div class="recovery-modal" style="background: #fff; border-radius: var(--radius); padding: 24px; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto;">
        <button type="button" class="modal-close" onclick="closeEditModal()" style="position: absolute; top: 12px; right: 12px; background: none; border: none; font-size: 24px; cursor: pointer; color: var(--muted);">&times;</button>
        <h3 style="margin: 0 0 16px; font-size: 16px; font-weight: 700; color: var(--navy);">Edit Subject</h3>
        <form method="post" action="/registrar/process_subject.php" id="editForm">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-grid" style="display: grid; grid-template-columns: 1fr; gap: 14px;">
                <div class="form-field">
                    <label for="edit_subject_code">Subject Code *</label>
                    <input type="text" id="edit_subject_code" name="subject_code" required style="height: 40px;">
                </div>
                <div class="form-field">
                    <label for="edit_subject_name">Subject Name *</label>
                    <input type="text" id="edit_subject_name" name="subject_name" required style="height: 40px;">
                </div>
                <div class="form-field">
                    <label for="edit_teacher_id">Teacher</label>
                    <select id="edit_teacher_id" name="teacher_id" style="height: 40px;">
                        <option value="">-- Unassigned --</option>
                        <?php foreach ($teachers as $t): ?>
                            <option value="<?= (int) $t['id'] ?>"><?= htmlspecialchars($t['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-field">
                    <label for="edit_schedule_grid">Schedule *</label>
                    <input type="text" id="edit_schedule_grid" name="schedule_grid" required placeholder="e.g. Mon 08:00-10:00, Wed 13:00-15:00" style="height: 40px;">
                </div>
                <div class="form-field">
                    <label for="edit_room">Room *</label>
                    <input type="text" id="edit_room" name="room" required placeholder="e.g. Room 101" style="height: 40px;">
                </div>
                <div class="form-field">
                    <label for="edit_capacity">Capacity *</label>
                    <input type="number" id="edit_capacity" name="capacity" min="1" max="100" required style="height: 40px;">
                </div>
                <div class="form-field">
                    <label for="edit_units">Units</label>
                    <input type="number" id="edit_units" name="units" min="0" max="10" style="height: 40px;">
                </div>
                <div class="form-field">
                    <label for="edit_adviser_section">Adviser Section</label>
                    <input type="text" id="edit_adviser_section" name="adviser_section" placeholder="e.g. BSCS-2A" style="height: 40px;">
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
function openEditModal(id, subjectCode, subjectName, scheduleGrid, room, capacity, units, adviserSection) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_subject_code').value = subjectCode;
    document.getElementById('edit_subject_name').value = subjectName;
    document.getElementById('edit_schedule_grid').value = scheduleGrid;
    document.getElementById('edit_room').value = room;
    document.getElementById('edit_capacity').value = capacity;
    document.getElementById('edit_units').value = units || '';
    document.getElementById('edit_adviser_section').value = adviserSection || '';
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