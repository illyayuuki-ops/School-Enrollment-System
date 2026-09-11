<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_role('registrar');
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
        SELECT s.student_number, u.full_name, s.program, s.year_level, s.enrollment_status
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

$pageTitle = 'Student Masterlist';
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
        <section class="content-grid">
            <article class="panel panel-wide">
                <div class="panel-heading"><div><span class="eyebrow">Records</span><h2>Student Masterlist</h2></div></div>

                <div class="search-bar">
                    <form method="get" action="/registrar/students.php" class="form-field" style="flex: 1; min-width: 250px;">
                        <label for="search" class="eyebrow">Search</label>
                        <input type="search" id="search" name="search" placeholder="Student number or name..." value="<?= htmlspecialchars($search) ?>" style="height: 40px;">
                    </form>
                    <span style="color: var(--muted); font-size: 13px;"><?= number_format($total) ?> student<?= $total !== 1 ? 's' : '' ?> found</span>
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
                                    <th>Year Level</th>
                                    <th>Enrollment Status</th>
                                    <th style="text-align: center;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $s): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($s['student_number']) ?></strong></td>
                                        <td><?= htmlspecialchars($s['full_name']) ?></td>
                                        <td><?= htmlspecialchars($s['program']) ?></td>
                                        <td><?= htmlspecialchars($s['year_level']) ?></td>
                                        <td>
                                            <span class="status-badge status-<?= $s['enrollment_status'] === 'enrolled' ? 'paid' : ($s['enrollment_status'] === 'pending' ? 'partial' : 'unpaid') ?>" style="font-size: 11px;">
                                                <?= htmlspecialchars(ucfirst($s['enrollment_status'])) ?>
                                            </span>
                                        </td>
                                        <td style="text-align: center;">
                                            <a href="/registrar/payments.php?student_id=<?= urlencode($s['student_number']) ?>" class="button button-secondary" style="font-size: 11px; padding: 4px 10px;">View Balance</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($totalPages > 1): ?>
                        <div style="display: flex; justify-content: center; gap: 8px; margin-top: 16px;">
                            <?php if ($page > 1): ?>
                                <a href="/registrar/students.php?page=<?= $page - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>" class="button button-secondary" style="font-size: 12px;">← Previous</a>
                            <?php endif; ?>
                            <span style="display: flex; align-items: center; color: var(--muted);">Page <?= $page ?> of <?= $totalPages ?></span>
                            <?php if ($page < $totalPages): ?>
                                <a href="/registrar/students.php?page=<?= $page + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>" class="button button-secondary" style="font-size: 12px;">Next →</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </article>
        </section>
    </main>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>