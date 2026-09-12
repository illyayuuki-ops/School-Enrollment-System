<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_role('admin');
require_once dirname(__DIR__) . '/db_connect.php';

$pdo = db_connect();

$search = trim($_GET['search'] ?? '');
$actionFilter = $_GET['action'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$where = '';
$params = [];

if ($search !== '') {
    $where = 'WHERE u.full_name ILIKE :search OR sl.action ILIKE :search OR sl.detail ILIKE :search';
    $params['search'] = "%{$search}%";
}

if ($actionFilter !== '') {
    if ($where) {
        $where .= ' AND sl.action = :action';
    } else {
        $where = 'WHERE sl.action = :action';
    }
    $params['action'] = $actionFilter;
}

// Total count
$total = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM system_logs sl LEFT JOIN users u ON sl.actor_user_id = u.id {$where}");
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();
} catch (PDOException $e) {
    $total = 0;
}
$totalPages = max(1, (int) ceil($total / $perPage));
if ($page > $totalPages) $page = $totalPages;

// Fetch logs
$logs = [];
try {
    $stmt = $pdo->prepare("
        SELECT sl.id, sl.action, sl.detail, sl.created_at, u.full_name as actor_name
        FROM system_logs sl
        LEFT JOIN users u ON sl.actor_user_id = u.id
        {$where}
        ORDER BY sl.created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll();
} catch (PDOException $e) {
    // Empty on error
}

// Get distinct actions for filter dropdown
$actions = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT action FROM system_logs ORDER BY action");
    $actions = array_column($stmt->fetchAll(), 'action');
} catch (PDOException $e) {}

$pageTitle = 'System Logs';
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
                <div class="panel-heading"><div><span class="eyebrow">Audit</span><h2>System Logs</h2></div></div>

                <div class="search-bar">
                    <form method="get" action="/admin/logs.php" class="form-field" style="flex: 1; min-width: 250px;">
                        <label for="search" class="eyebrow">Search</label>
                        <input type="search" id="search" name="search" placeholder="Actor, action, or detail..." value="<?= htmlspecialchars($search) ?>" style="height: 40px;">
                    </form>
                    <form method="get" action="/admin/logs.php" style="display: flex; align-items: end; gap: 8px;">
                        <?php if ($search): ?><input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
                        <div class="form-field" style="min-width: 160px;">
                            <label for="action_filter" class="eyebrow">Action</label>
                            <select id="action_filter" name="action" style="height: 40px;">
                                <option value="">All Actions</option>
                                <?php foreach ($actions as $a): ?>
                                    <option value="<?= htmlspecialchars($a) ?>" <?= $actionFilter === $a ? 'selected' : '' ?>><?= htmlspecialchars($a) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button class="button button-primary" type="submit" style="height: 40px;">Filter</button>
                    </form>
                    <span style="color: var(--muted); font-size: 13px;"><?= number_format($total) ?> log<?= $total !== 1 ? 's' : '' ?></span>
                </div>

                <?php if (empty($logs)): ?>
                    <div class="empty-state">
                        <p>No system logs found<?= $search ? ' matching "' . htmlspecialchars($search) . '"' : '' ?><?= $actionFilter ? ' for action ' . htmlspecialchars($actionFilter) : '' ?>.</p>
                    </div>
                <?php else: ?>
                    <div class="table-wrapper" style="overflow-x: auto;">
                        <table class="logs-table">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Actor</th>
                                    <th class="action-cell">Action</th>
                                    <th class="detail-cell">Detail</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td style="white-space: nowrap;"><?= date('M d, Y H:i:s', strtotime($log['created_at'])) ?></td>
                                        <td><?= htmlspecialchars($log['actor_name'] ?? 'System') ?></td>
                                        <td class="action-cell"><?= htmlspecialchars($log['action']) ?></td>
                                        <td class="detail-cell"><?= htmlspecialchars($log['detail'] ?? '') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($totalPages > 1): ?>
                        <div style="display: flex; justify-content: center; gap: 8px; margin-top: 16px;">
                            <?php if ($page > 1): ?>
                                <a href="/admin/logs.php?page=<?= $page - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $actionFilter ? '&action=' . urlencode($actionFilter) : '' ?>" class="button button-secondary" style="font-size: 12px;">← Previous</a>
                            <?php endif; ?>
                            <span style="display: flex; align-items: center; color: var(--muted);">Page <?= $page ?> of <?= $totalPages ?></span>
                            <?php if ($page < $totalPages): ?>
                                <a href="/admin/logs.php?page=<?= $page + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $actionFilter ? '&action=' . urlencode($actionFilter) : '' ?>" class="button button-secondary" style="font-size: 12px;">Next →</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </article>
        </section>
    </main>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>