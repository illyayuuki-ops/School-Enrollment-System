<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_role('admission');
require_once dirname(__DIR__) . '/db_connect.php';

$pdo = db_connect();

$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? 'pending';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = '';
$params = [];

if ($statusFilter !== 'all') {
    $where = 'WHERE pr.status = :status';
    $params['status'] = $statusFilter;
} else {
    $where = '';
}

if ($search !== '') {
    if ($where) {
        $where .= ' AND ';
    } else {
        $where = 'WHERE ';
    }
    $where .= '(pr.application_number ILIKE :search OR u.full_name ILIKE :search OR pr.email ILIKE :search)';
    $params['search'] = "%{$search}%";
}

// Total count
$total = 0;
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM pre_registrations pr
        JOIN users u ON pr.email = u.email
        {$where}
    ");
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();
} catch (PDOException $e) {
    $total = 0;
}
$totalPages = max(1, (int) ceil($total / $perPage));
if ($page > $totalPages) $page = $totalPages;

// Fetch applications
$applications = [];
try {
    $stmt = $pdo->prepare("
        SELECT pr.*, u.full_name
        FROM pre_registrations pr
        LEFT JOIN users u ON pr.email = u.email
        {$where}
        ORDER BY pr.created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $applications = $stmt->fetchAll();
} catch (PDOException $e) {
    // Empty on error
}

$pageTitle = 'Verification Queue';
$extraStyles = ['/assets/admission.css'];
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="app-frame">
    <?php require dirname(__DIR__) . '/includes/sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div><span class="eyebrow">Admissions workspace</span><h1>Good morning, <?= htmlspecialchars(explode(' ', $_SESSION['user']['full_name'])[0], ENT_QUOTES, 'UTF-8') ?>.</h1></div>
            <div class="topbar-actions"><span class="year-pill">A.Y. 2026–2027</span><a class="avatar" href="/logout.php" title="Sign out"><?= strtoupper(substr((string) $_SESSION['user']['full_name'], 0, 1)) ?></a></div>
        </header>
        <section class="content-grid">
            <article class="panel panel-wide">
                <div class="panel-heading"><div><span class="eyebrow">Applications</span><h2>Verification Queue</h2></div></div>

                <div class="search-bar">
                    <form method="get" action="/admission/queue.php" class="form-field" style="flex: 1; min-width: 250px;">
                        <label for="search" class="eyebrow">Search</label>
                        <input type="search" id="search" name="search" placeholder="Application #, name, or email..." value="<?= htmlspecialchars($search) ?>" style="height: 40px;">
                    </form>
                    <form method="get" action="/admission/queue.php" style="display: flex; align-items: end; gap: 8px;">
                        <div class="form-field" style="min-width: 160px;">
                            <label for="status" class="eyebrow">Status</label>
                            <select id="status" name="status" style="height: 40px;">
                                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
                                <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All</option>
                            </select>
                        </div>
                        <button class="button button-primary" type="submit" style="height: 40px;">Filter</button>
                    </form>
                    <span style="color: var(--muted); font-size: 13px;"><?= number_format($total) ?> application<?= $total !== 1 ? 's' : '' ?></span>
                </div>

                <?php if (empty($applications)): ?>
                    <div class="empty-state">
                        <p>No applications found<?= $search ? ' matching "' . htmlspecialchars($search) . '"' : '' ?><?= $statusFilter !== 'all' ? ' with status ' . ucfirst($statusFilter) : '' ?>.</p>
                    </div>
                <?php else: ?>
                    <div class="table-wrapper" style="overflow-x: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Application #</th>
                                    <th>Applicant Name</th>
                                    <th>Level Type</th>
                                    <th>Course</th>
                                    <th>Email</th>
                                    <th>Submitted</th>
                                    <th>Status</th>
                                    <th style="text-align: center;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($applications as $app): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($app['application_number'] ?? '—') ?></strong></td>
                                        <td><?= htmlspecialchars($app['full_name'] ?? $app['last_name'] . ' ' . $app['first_name'] ?? '—') ?></td>
                                        <td><?= htmlspecialchars($app['level_type'] ?? '—') ?></td>
                                        <td><?= htmlspecialchars($app['first_choice_course'] ?? '—') ?></td>
                                        <td><?= htmlspecialchars($app['email'] ?? '—') ?></td>
                                        <td><?= date('M d, Y H:i', strtotime($app['created_at'] ?? 'now')) ?></td>
                                        <td>
                                            <span class="status-badge status-<?= htmlspecialchars($app['status'] ?? 'pending') ?>">
                                                <?= htmlspecialchars(ucfirst($app['status'] ?? 'pending')) ?>
                                            </span>
                                        </td>
                                        <td style="text-align: center;">
                                            <div class="action-buttons">
                                                <a href="/admission/documents.php?id=<?= urlencode($app['id'] ?? '') ?>" class="btn-view">View</a>
                                                <?php if (($app['status'] ?? 'pending') === 'pending'): ?>
                                                    <a href="/admission/process_application.php?id=<?= urlencode($app['id'] ?? '') ?>&action=approve" class="btn-approve" onclick="return confirm('Approve this application?');">Approve</a>
                                                    <a href="/admission/process_application.php?id=<?= urlencode($app['id'] ?? '') ?>&action=reject" class="btn-reject" onclick="return confirm('Reject this application?');">Reject</a>
                                                <?php endif; ?>
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
                                <a href="/admission/queue.php?page=<?= $page - 1 ?><?= $statusFilter !== 'all' ? '&status=' . urlencode($statusFilter) : '' ?><?= $search ? '&search=' . urlencode($search) : '' ?>" class="button button-secondary" style="font-size: 12px;">← Previous</a>
                            <?php endif; ?>
                            <span style="display: flex; align-items: center; color: var(--muted);">Page <?= $page ?> of <?= $totalPages ?></span>
                            <?php if ($page < $totalPages): ?>
                                <a href="/admission/queue.php?page=<?= $page + 1 ?><?= $statusFilter !== 'all' ? '&status=' . urlencode($statusFilter) : '' ?><?= $search ? '&search=' . urlencode($search) : '' ?>" class="button button-secondary" style="font-size: 12px;">Next →</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </article>
        </section>
    </main>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>