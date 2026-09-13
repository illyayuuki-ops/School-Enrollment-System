<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_role('admission');
require_once dirname(__DIR__) . '/db_connect.php';
require_once dirname(__DIR__) . '/includes/settings.php';

$pdo = db_connect();

$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = 'WHERE pr.status = :status';
$params = ['status' => 'pending'];

if ($search !== '') {
    $where .= ' AND (pr.application_number ILIKE :search OR CONCAT(pr.last_name, \' \', pr.first_name) ILIKE :search OR pr.email ILIKE :search)';
    $params['search'] = "%{$search}%";
}

// Total count
$total = 0;
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM pre_registrations pr
        {$where}
    ");
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();
} catch (PDOException $e) {
    $total = 0;
}
$totalPages = max(1, (int) ceil($total / $perPage));
if ($page > $totalPages) $page = $totalPages;

// Fetch pre-admission leads
$leads = [];
try {
    $stmt = $pdo->prepare("
        SELECT pr.*, CONCAT(pr.last_name, ' ', pr.first_name) AS full_name
        FROM pre_registrations pr
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
    $leads = $stmt->fetchAll();
} catch (PDOException $e) {
    // Empty on error
}

$pageTitle = 'Pre-Admission List';
$extraStyles = ['/assets/admission.css'];
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="app-frame">
    <?php require dirname(__DIR__) . '/includes/sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div><span class="eyebrow">Admissions workspace</span><h1>Welcome, <?= htmlspecialchars(explode(' ', $_SESSION['user']['full_name'])[0], ENT_QUOTES, 'UTF-8') ?>.</h1></div>
            <div class="topbar-actions"><span class="year-pill"><?= htmlspecialchars(get_academic_year($pdo)) ?></span><a class="avatar" href="#" onclick="return openLogoutModal()" title="Sign out"><?= strtoupper(substr((string) $_SESSION['user']['full_name'], 0, 1)) ?></a></div>
        </header>
        <section class="content-grid">
            <article class="panel panel-wide">
                <div class="panel-heading"><div><span class="eyebrow">Pre-Admission</span><h2>Pre-Admission List</h2></div></div>

                <div class="search-bar">
                    <form method="get" action="/admission/pre_admission.php" class="form-field" style="flex: 1; min-width: 250px;">
                        <label for="search" class="eyebrow">Search</label>
                        <input type="search" id="search" name="search" placeholder="Application #, name, or email..." value="<?= htmlspecialchars($search) ?>" style="height: 40px;">
                    </form>
                    <span style="color: var(--muted); font-size: 13px;"><?= number_format($total) ?> lead<?= $total !== 1 ? 's' : '' ?> pending</span>
                </div>

                <?php if (empty($leads)): ?>
                    <div class="empty-state">
                        <p>No pending pre-admission leads<?= $search ? ' matching "' . htmlspecialchars($search) . '"' : '' ?>.</p>
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
                                    <th>Contact Number</th>
                                    <th>Submitted</th>
                                    <th style="text-align: center;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($leads as $lead): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($lead['application_number'] ?? '—') ?></strong></td>
                                        <td><?= htmlspecialchars($lead['last_name'] . ' ' . $lead['first_name']) ?></td>
                                        <td><?= htmlspecialchars($lead['level_type'] ?? '—') ?></td>
                                        <td><?= htmlspecialchars($lead['first_choice_course'] ?? '—') ?></td>
                                        <td><?= htmlspecialchars($lead['email'] ?? '—') ?></td>
                                        <td><?= htmlspecialchars($lead['contact_number'] ?? '—') ?></td>
                                        <td><?= date('M d, Y H:i', strtotime($lead['created_at'] ?? 'now')) ?></td>
                                        <td style="text-align: center;">
                                            <div class="action-buttons">
                                                <a href="/admission/documents.php?id=<?= urlencode($lead['id'] ?? '') ?>" class="btn-view">View</a>
                                                <a href="/admission/process_application.php?id=<?= urlencode($lead['id'] ?? '') ?>&action=approve" class="btn-approve" onclick="return confirm('Approve this pre-admission lead?');">Approve</a>
                                                <form method="post" action="/admission/process_application.php" style="display: inline;" onsubmit="return confirm('Reject this pre-admission lead?');">
                                                    <input type="hidden" name="id" value="<?= htmlspecialchars($lead['id'] ?? '') ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                                    <button type="submit" class="btn-reject">Reject</button>
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
                                <a href="/admission/pre_admission.php?page=<?= $page - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>" class="button button-secondary" style="font-size: 12px;">← Previous</a>
                            <?php endif; ?>
                            <span style="display: flex; align-items: center; color: var(--muted);">Page <?= $page ?> of <?= $totalPages ?></span>
                            <?php if ($page < $totalPages): ?>
                                <a href="/admission/pre_admission.php?page=<?= $page + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>" class="button button-secondary" style="font-size: 12px;">Next →</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </article>
        </section>
    </main>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>