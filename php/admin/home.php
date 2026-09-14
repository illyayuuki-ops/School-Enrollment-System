<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_role('admin');
require_once dirname(__DIR__) . '/db_connect.php';
require_once dirname(__DIR__) . '/includes/settings.php';

$pdo = db_connect();

$totalUsers = 0;
$roleCounts = [
    'admin' => 0,
    'admission' => 0,
    'registrar' => 0,
    'teacher' => 0,
    'student' => 0,
];
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users");
    $stmt->execute();
    $totalUsers = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT role, COUNT(*) as cnt FROM users GROUP BY role");
    $stmt->execute();
    foreach ($stmt->fetchAll() as $row) {
        $roleCounts[$row['role']] = (int) $row['cnt'];
    }
} catch (PDOException $e) {
    // Keep defaults on error
}

$pageTitle = 'Administrator Overview';
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
        <section class="dashboard-grid">
            <article class="metric-card"><span class="eyebrow">Total users</span><strong><?= number_format($totalUsers) ?></strong><small>Across all roles</small></article>
            <article class="metric-card"><span class="eyebrow">Administrators</span><strong><?= number_format($roleCounts['admin']) ?></strong><small><?= number_format($roleCounts['admission']) ?> Admissions</small></article>
            <article class="metric-card"><span class="eyebrow">Registrars</span><strong><?= number_format($roleCounts['registrar']) ?></strong><small><?= number_format($roleCounts['teacher']) ?> Teachers</small></article>
            <article class="metric-card accent-card"><span class="eyebrow">Students</span><strong><?= number_format($roleCounts['student']) ?></strong><small>Enrolled users</small></article>
        </section>
        <section class="content-grid">
            <article class="panel panel-wide">
                <div class="panel-heading"><div><span class="eyebrow">Users per Role</span><h2>Role Distribution</h2></div></div>
                <?php
                $roleLabels = ['admin' => 'Admin', 'admission' => 'Admission', 'registrar' => 'Registrar', 'teacher' => 'Teacher', 'student' => 'Student'];
                $roleColors = ['admin' => '#3b82f6', 'admission' => '#f59e0b', 'registrar' => '#10b981', 'teacher' => '#8b5cf6', 'student' => '#ec4899'];
                $maxRole = max($roleCounts);
                if ($maxRole > 0): ?>
                    <div style="display:flex;align-items:flex-end;gap:16px;height:200px;padding:16px 0;">
                        <?php foreach ($roleCounts as $role => $count): ?>
                            <div style="display:flex;flex-direction:column;align-items:center;gap:8px;flex:1;height:100%;justify-content:flex-end;">
                                <div style="width:100%;height:<?= round(($count / $maxRole) * 100, 1) ?>%;background:<?= $roleColors[$role] ?? '#6366f1' ?>;border-radius:4px 4px 0 0;min-height:4px;display:flex;align-items:center;justify-content:center;">
                                    <span style="color:#fff;font-size:12px;font-weight:700;"><?= number_format($count) ?></span>
                                </div>
                                <span style="font-size:11px;color:var(--muted);text-align:center;"><?= htmlspecialchars($roleLabels[$role] ?? $role) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </article>
            <article class="panel">
                <div class="panel-heading"><div><span class="eyebrow">Recent activity</span><h2>System events</h2></div></div>
                <ul class="activity-list">
                    <li><span class="activity-dot green"></span><span><strong>System ready</strong><small>Admin portal initialized</small></span></li>
                </ul>
            </article>
        </section>
    </main>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>