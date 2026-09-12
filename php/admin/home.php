<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_role('admin');
require_once dirname(__DIR__) . '/db_connect.php';

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
            <div class="topbar-actions"><span class="year-pill">A.Y. 2026–2027</span><a class="avatar" href="#" onclick="return openLogoutModal()" title="Sign out"><?= strtoupper(substr((string) $_SESSION['user']['full_name'], 0, 1)) ?></a></div>
        </header>
        <section class="dashboard-grid">
            <article class="metric-card"><span class="eyebrow">Total users</span><strong><?= number_format($totalUsers) ?></strong><small>Across all roles</small></article>
            <article class="metric-card"><span class="eyebrow">Administrators</span><strong><?= number_format($roleCounts['admin']) ?></strong><small><?= number_format($roleCounts['admission']) ?> Admissions</small></article>
            <article class="metric-card"><span class="eyebrow">Registrars</span><strong><?= number_format($roleCounts['registrar']) ?></strong><small><?= number_format($roleCounts['teacher']) ?> Teachers</small></article>
            <article class="metric-card accent-card"><span class="eyebrow">Students</span><strong><?= number_format($roleCounts['student']) ?></strong><small>Enrolled users</small><div class="progress"><span style="width:<?= $totalUsers > 0 ? min(100, ($roleCounts['student'] / $totalUsers) * 100) : 0 ?>%"></span></div></article>
        </section>
        <section class="content-grid">
            <article class="panel panel-wide">
                <div class="panel-heading"><div><span class="eyebrow">Quick actions</span><h2>Manage the academy</h2></div></div>
                <div class="action-list">
                    <a href="/admin/users.php"><span class="action-icon blue">01</span><span><strong>User accounts</strong><small>Create, edit, suspend accounts</small></span><span class="arrow">↗</span></a>
                    <a href="/admin/logs.php"><span class="action-icon green">02</span><span><strong>System logs</strong><small>Review activity and errors</small></span><span class="arrow">↗</span></a>
                    <a href="/admin/settings.php"><span class="action-icon amber">03</span><span><strong>Global settings</strong><small>Configure academy parameters</small></span><span class="arrow">↗</span></a>
                </div>
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