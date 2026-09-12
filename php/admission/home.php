<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_role('admission');
require_once dirname(__DIR__) . '/db_connect.php';

$pdo = db_connect();

// Live metrics from pre_registrations
$pendingCount = 0;
$approvedCount = 0;
$rejectedCount = 0;
$totalCount = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM pre_registrations WHERE status = 'pending'");
    $stmt->execute();
    $pendingCount = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM pre_registrations WHERE status = 'approved'");
    $stmt->execute();
    $approvedCount = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM pre_registrations WHERE status = 'rejected'");
    $stmt->execute();
    $rejectedCount = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM pre_registrations");
    $stmt->execute();
    $totalCount = (int) $stmt->fetchColumn();
} catch (PDOException $e) {
    // Keep defaults on error
}

$conversionRate = $totalCount > 0 ? round(($approvedCount / $totalCount) * 100, 1) : 0;

$pageTitle = 'Admissions Overview';
$extraStyles = ['/assets/admission.css'];
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="app-frame">
    <?php require dirname(__DIR__) . '/includes/sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div><span class="eyebrow">Admissions workspace</span><h1>Welcome, <?= htmlspecialchars(explode(' ', $_SESSION['user']['full_name'])[0], ENT_QUOTES, 'UTF-8') ?>.</h1></div>
            <div class="topbar-actions"><span class="year-pill">A.Y. 2026–2027</span><a class="avatar" href="#" onclick="return openLogoutModal()" title="Sign out"><?= strtoupper(substr((string) $_SESSION['user']['full_name'], 0, 1)) ?></a></div>
        </header>
        <section class="dashboard-grid">
            <article class="metric-card"><span class="eyebrow">Total applications</span><strong><?= number_format($totalCount) ?></strong><small><?= $pendingCount ?> pending review</small></article>
            <article class="metric-card"><span class="eyebrow">Pending review</span><strong><?= number_format($pendingCount) ?></strong><small><?= $approvedCount ?> approved, <?= $rejectedCount ?> rejected</small></article>
            <article class="metric-card"><span class="eyebrow">Approved</span><strong><?= number_format($approvedCount) ?></strong><small>Conversion rate: <?= $conversionRate ?>%</small></article>
            <article class="metric-card accent-card"><span class="eyebrow">Conversion rate</span><strong><?= $conversionRate ?>%</strong></article>
        </section>
        <section class="content-grid">
            <article class="panel panel-wide">
                <div class="panel-heading"><div><span class="eyebrow">Quick actions</span><h2>Process Applications</h2></div></div>
                <div class="action-list">
                    <a href="/admission/queue.php"><span class="action-icon blue">01</span><span><strong>Verification Queue</strong><small>Review pending applications</small></span><span class="arrow">↗</span></a>
                    <a href="/admission/documents.php"><span class="action-icon green">02</span><span><strong>Document Review</strong><small>Check submitted requirements</small></span><span class="arrow">↗</span></a>
                    <a href="/admission/queue.php"><span class="action-icon amber">03</span><span><strong>Bulk Actions</strong><small>Approve or request more info</small></span><span class="arrow">↗</span></a>
                </div>
            </article>
            <article class="panel">
                <div class="panel-heading"><div><span class="eyebrow">Recent Activity</span><h2>Application Updates</h2></div></div>
                <ul class="activity-list">
                    <li><span class="activity-dot green"></span><span><strong>Application verified</strong><small>Maria Santos · 8 min ago</small></span></li>
                    <li><span class="activity-dot blue"></span><span><strong>New application received</strong><small>Daniel Tan · 24 min ago</small></span></li>
                    <li><span class="activity-dot amber"></span><span><strong>Document returned</strong><small>Leah Wong · 1 hr ago</small></span></li>
                </ul>
            </article>
        </section>
    </main>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>