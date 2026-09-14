<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_role('admin');
require_once dirname(__DIR__) . '/db_connect.php';
require_once dirname(__DIR__) . '/includes/settings.php';

$pdo = db_connect();

$search = trim($_GET['search'] ?? '');
$roleFilter = $_GET['role'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = '';
$params = [];

if ($search !== '') {
    $where = 'WHERE u.email ILIKE :search OR u.full_name ILIKE :search';
    $params['search'] = "%{$search}%";
}

if ($roleFilter !== '') {
    if ($where) {
        $where .= ' AND u.role = :role';
    } else {
        $where = 'WHERE u.role = :role';
    }
    $params['role'] = $roleFilter;
}

if ($statusFilter !== '') {
    if ($where) {
        $where .= ' AND u.status = :status';
    } else {
        $where = 'WHERE u.status = :status';
    }
    $params['status'] = $statusFilter;
}

// Total count
$total = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users u {$where}");
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();
} catch (PDOException $e) {
    $total = 0;
}
$totalPages = max(1, (int) ceil($total / $perPage));
if ($page > $totalPages) $page = $totalPages;

// Fetch users
$users = [];
try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.email, u.full_name, u.role, u.status, u.school_email, u.level_type, u.active_this_year, u.created_at
        FROM users u
        {$where}
        ORDER BY u.created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    // Empty on error
}

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
$newPassword = $_GET['new_password'] ?? '';

$pageTitle = 'User Accounts';
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
                <div class="panel-heading"><div><span class="eyebrow">User Management</span><h2>User Accounts</h2></div></div>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                <?php if ($newPassword): ?>
                    <div class="alert alert-success" style="margin-top: 12px;">
                        <strong>New temporary password:</strong> <code><?= htmlspecialchars($newPassword) ?></code>
                        <p style="margin: 8px 0 0; font-size: 12px; color: var(--muted);">Share this password with the user. They will be prompted to change it on first login.</p>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <!-- Create User Form -->
                <div class="user-form">
                    <h3 style="margin: 0 0 16px; font-size: 14px; font-weight: 700; color: var(--navy);">Create New User</h3>
                    <form method="post" action="/admin/process_user.php" class="user-form-inner">
                        <input type="hidden" name="action" value="create">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

                        <div class="form-grid">
                            <div class="form-field">
                                <label for="email">Email *</label>
                                <input type="email" id="email" name="email" required placeholder="user@sma.edu.ph" style="height: 40px;">
                            </div>
                            <div class="form-field">
                                <label for="full_name">Full Name *</label>
                                <input type="text" id="full_name" name="full_name" required placeholder="Juan Dela Cruz" style="height: 40px;">
                            </div>
                            <div class="form-field">
                                <label for="role">Role *</label>
                                <select id="role" name="role" required style="height: 40px;">
                                    <option value="">-- Select Role --</option>
                                    <option value="admin">Admin</option>
                                    <option value="admission">Admission</option>
                                    <option value="registrar">Registrar</option>
                                    <option value="teacher">Teacher</option>
                                    <option value="student">Student</option>
                                </select>
                            </div>
                            <div class="form-field">
                                <label for="password">Temporary Password *</label>
                                <input type="password" id="password" name="password" required placeholder="Auto-generated if left blank" style="height: 40px;">
                            </div>
                            <div class="form-field">
                                <label for="school_email">School Email</label>
                                <input type="email" id="school_email" name="school_email" placeholder="user@sma.edu.ph" style="height: 40px;">
                            </div>
                            <div class="form-field" id="create_level_type_field" style="display:none;">
                                <label for="level_type">Level Type</label>
                                <select id="level_type" name="level_type" style="height: 40px;">
                                    <option value="">-- Select Level Type --</option>
                                    <option value="BED">BED</option>
                                    <option value="College">College</option>
                                    <option value="TESDA">TESDA</option>
                                </select>
                            </div>
                            <div class="form-field" id="create_active_year_field" style="display:none;">
                                <label for="active_this_year">Active This Year</label>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <input type="checkbox" id="active_this_year" name="active_this_year" value="1" checked>
                                    <label for="active_this_year" style="margin: 0; font-weight: 500; font-size: 13px;">Active this academic year</label>
                                </div>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn-create">Create User</button>
                        </div>
                    </form>
                </div>

                <!-- Users List -->
                <div class="search-bar" style="margin-top: 24px;">
                    <form method="get" action="/admin/users.php" class="form-field" style="flex: 1; min-width: 250px;">
                        <label for="search" class="eyebrow">Search</label>
                        <input type="search" id="search" name="search" placeholder="Email or name..." value="<?= htmlspecialchars($search) ?>" style="height: 40px;">
                    </form>
                    <form method="get" action="/admin/users.php" style="display: flex; align-items: end; gap: 8px;">
                        <?php if ($search): ?><input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
                        <div class="form-field" style="min-width: 140px;">
                            <label for="role_filter" class="eyebrow">Role</label>
                            <select id="role_filter" name="role" style="height: 40px;">
                                <option value="">All Roles</option>
                                <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Admin</option>
                                <option value="admission" <?= $roleFilter === 'admission' ? 'selected' : '' ?>>Admission</option>
                                <option value="registrar" <?= $roleFilter === 'registrar' ? 'selected' : '' ?>>Registrar</option>
                                <option value="teacher" <?= $roleFilter === 'teacher' ? 'selected' : '' ?>>Teacher</option>
                                <option value="student" <?= $roleFilter === 'student' ? 'selected' : '' ?>>Student</option>
                            </select>
                        </div>
                        <div class="form-field" style="min-width: 140px;">
                            <label for="status_filter" class="eyebrow">Status</label>
                            <select id="status_filter" name="status" style="height: 40px;">
                                <option value="">All Status</option>
                                <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="suspended" <?= $statusFilter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                            </select>
                        </div>
                        <button class="button button-primary" type="submit" style="height: 40px;">Filter</button>
                    </form>
                    <span style="color: var(--muted); font-size: 13px;"><?= number_format($total) ?> user<?= $total !== 1 ? 's' : '' ?></span>
                </div>

                <?php if (empty($users)): ?>
                    <div class="empty-state">
                        <p>No users found<?= $search ? ' matching "' . htmlspecialchars($search) . '"' : '' ?><?= $roleFilter ? ' with role ' . ucfirst($roleFilter) : '' ?><?= $statusFilter ? ' and status ' . ucfirst($statusFilter) : '' ?>.</p>
                    </div>
                <?php else: ?>
                    <div class="table-wrapper" style="overflow-x: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Email</th>
                                    <th>Full Name</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>School Email</th>
                                    <th>Level Type</th>
                                    <th>Active This Year</th>
                                    <th>Created</th>
                                    <th style="text-align: center;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $u): ?>
                                    <tr>
                                        <td><?= (int) $u['id'] ?></td>
                                        <td><?= htmlspecialchars($u['email']) ?></td>
                                        <td><?= htmlspecialchars($u['full_name']) ?></td>
                                        <td>
                                            <span class="status-badge status-<?= $u['role'] === 'admin' ? 'approved' : ($u['role'] === 'student' ? 'pending' : ($u['role'] === 'teacher' ? 'active' : ($u['role'] === 'admission' ? 'verified' : 'partial'))) ?>" style="font-size: 11px; text-transform: capitalize;">
                                                <?= htmlspecialchars($u['role']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?= htmlspecialchars($u['status']) ?>">
                                                <?= htmlspecialchars(ucfirst($u['status'])) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($u['school_email'] ?? '—') ?></td>
                                        <td><?= htmlspecialchars($u['level_type'] ?? '—') ?></td>
                                        <td>
                                            <span class="status-badge status-<?= $u['active_this_year'] ? 'paid' : 'unpaid' ?>" style="font-size: 11px; text-transform: capitalize;">
                                                <?= $u['active_this_year'] ? 'Yes' : 'No' ?>
                                            </span>
                                        </td>
                                        <td><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                                        <td style="text-align: center;">
                                            <div class="action-buttons">
                                                <button type="button" class="btn-edit" onclick="openEditModal(<?= (int) $u['id'] ?>, '<?= htmlspecialchars($u['email'], ENT_QUOTES) ?>', '<?= htmlspecialchars($u['full_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($u['role'], ENT_QUOTES) ?>', '<?= htmlspecialchars($u['status'], ENT_QUOTES) ?>', '<?= htmlspecialchars($u['school_email'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($u['level_type'] ?? '', ENT_QUOTES) ?>', '<?= $u['active_this_year'] ? '1' : '0' ?>')" style="padding: 4px 10px; font-size: 11px;">Edit</button>
<form method="post" action="/admin/process_user.php" style="display: inline;">
                                                     <input type="hidden" name="action" value="reset_password">
                                                     <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                                     <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                     <button type="submit" class="btn-edit" onclick="return confirm('Reset password for this user? A new temporary password will be generated.');" style="padding: 4px 10px; font-size: 11px;">Reset Password</button>
                                                 </form>
                                                 <form method="post" action="/admin/process_user.php" style="display: inline;">
                                                     <input type="hidden" name="action" value="force_activate">
                                                     <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                                     <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                     <button type="submit" class="btn-edit" style="padding: 4px 10px; font-size: 11px;" onclick="return confirm('Force activate this user account?');">Activate</button>
                                                 </form>
                                                 <form method="post" action="/admin/process_user.php" style="display: inline;">
                                                     <input type="hidden" name="action" value="toggle_status">
                                                     <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                                     <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                     <button type="submit" class="<?= $u['status'] === 'active' ? 'btn-suspend' : 'btn-reactivate' ?>" onclick="return confirm('Are you sure you want to <?= $u['status'] === 'active' ? 'suspend' : 'reactivate' ?> this user?');" style="padding: 4px 10px; font-size: 11px;">
                                                         <?= $u['status'] === 'active' ? 'Suspend' : 'Reactivate' ?>
                                                     </button>
                                                 </form>
                                                 <form method="post" action="/admin/process_user.php" style="display: inline;">
                                                     <input type="hidden" name="action" value="delete">
                                                     <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                                     <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                     <button type="submit" class="btn-suspend" onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.');" style="padding: 4px 10px; font-size: 11px;">Delete</button>
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
                                <a href="/admin/users.php?page=<?= $page - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $roleFilter ? '&role=' . urlencode($roleFilter) : '' ?><?= $statusFilter ? '&status=' . urlencode($statusFilter) : '' ?>" class="button button-secondary" style="font-size: 12px;">← Previous</a>
                            <?php endif; ?>
                            <span style="display: flex; align-items: center; color: var(--muted);">Page <?= $page ?> of <?= $totalPages ?></span>
                            <?php if ($page < $totalPages): ?>
                                <a href="/admin/users.php?page=<?= $page + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $roleFilter ? '&role=' . urlencode($roleFilter) : '' ?><?= $statusFilter ? '&status=' . urlencode($statusFilter) : '' ?>" class="button button-secondary" style="font-size: 12px;">Next →</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </article>
        </section>
    </main>
</div>

<!-- Edit User Modal -->
<div id="editModal" class="modal-backdrop" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div class="recovery-modal" style="background: #fff; border-radius: var(--radius); padding: 24px; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto;">
        <button type="button" class="modal-close" onclick="closeEditModal()" style="position: absolute; top: 12px; right: 12px; background: none; border: none; font-size: 24px; cursor: pointer; color: var(--muted);">&times;</button>
        <h3 style="margin: 0 0 16px; font-size: 16px; font-weight: 700; color: var(--navy);">Edit User</h3>
        <form method="post" action="/admin/process_user.php" id="editForm">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-grid" style="display: grid; grid-template-columns: 1fr; gap: 14px;">
                <div class="form-field">
                    <label for="edit_email">Email *</label>
                    <input type="email" id="edit_email" name="email" required style="height: 40px;">
                </div>
                <div class="form-field">
                    <label for="edit_full_name">Full Name *</label>
                    <input type="text" id="edit_full_name" name="full_name" required style="height: 40px;">
                </div>
                <div class="form-field">
                    <label for="edit_role">Role *</label>
                    <select id="edit_role" name="role" required style="height: 40px;">
                        <option value="admin">Admin</option>
                        <option value="admission">Admission</option>
                        <option value="registrar">Registrar</option>
                        <option value="teacher">Teacher</option>
                        <option value="student">Student</option>
                    </select>
                </div>
                <div class="form-field">
                    <label for="edit_status">Status *</label>
                    <select id="edit_status" name="status" required style="height: 40px;">
                        <option value="active">Active</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </div>
                <div class="form-field">
                    <label for="edit_school_email">School Email</label>
                    <input type="email" id="edit_school_email" name="school_email" placeholder="user@sma.edu.ph" style="height: 40px;">
                </div>
                <div class="form-field" id="edit_level_type_field" style="display:none;">
                    <label for="edit_level_type">Level Type</label>
                    <select id="edit_level_type" name="level_type" style="height: 40px;">
                        <option value="">-- Select Level Type --</option>
                        <option value="BED">BED</option>
                        <option value="College">College</option>
                        <option value="TESDA">TESDA</option>
                    </select>
                </div>
                <div class="form-field" id="edit_active_year_field" style="display:none;">
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
function openEditModal(id, email, fullName, role, status, schoolEmail, levelType, activeThisYear) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_full_name').value = fullName;
    document.getElementById('edit_role').value = role;
    document.getElementById('edit_status').value = status;
    document.getElementById('edit_school_email').value = schoolEmail || '';
    document.getElementById('edit_level_type').value = levelType || '';
    document.getElementById('edit_active_this_year').checked = activeThisYear === '1';
    toggleRoleFields();
    document.getElementById('editModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

function toggleRoleFields() {
    var createRole = document.getElementById('role');
    var editRole = document.getElementById('edit_role');
    var createStudent = createRole && createRole.value === 'student';
    var editStudent = editRole && editRole.value === 'student';
    var createLvl = document.getElementById('create_level_type_field');
    var createAct = document.getElementById('create_active_year_field');
    var editLvl = document.getElementById('edit_level_type_field');
    var editAct = document.getElementById('edit_active_year_field');
    if (createLvl) createLvl.style.display = createStudent ? '' : 'none';
    if (createAct) createAct.style.display = createStudent ? '' : 'none';
    if (editLvl) editLvl.style.display = editStudent ? '' : 'none';
    if (editAct) editAct.style.display = editStudent ? '' : 'none';
}

document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});

document.getElementById('role').addEventListener('change', toggleRoleFields);
document.getElementById('edit_role').addEventListener('change', toggleRoleFields);
toggleRoleFields();
</script>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>