<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_role('admin');
$pageTitle = 'Administrator Overview';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="app-frame">
    <?php require dirname(__DIR__) . '/includes/sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div><span class="eyebrow">Administrator workspace</span><h1>Good morning, <?= htmlspecialchars(explode(' ', $_SESSION['user']['full_name'])[0], ENT_QUOTES, 'UTF-8') ?>.</h1></div>
            <div class="topbar-actions"><span class="year-pill">A.Y. 2026–2027</span><a class="avatar" href="/logout.php" title="Sign out"><?= strtoupper(substr((string) $_SESSION['user']['full_name'], 0, 1)) ?></a></div>
        </header>
        <section class="dashboard-grid">
            <article class="metric-card"><span class="eyebrow">Total users</span><strong>1,284</strong><small><b class="trend-up">+8.4%</b> vs last month</small></article>
            <article class="metric-card"><span class="eyebrow">Active sessions</span><strong>342</strong><small><b class="trend-up">+12%</b> today</small></article>
            <article class="metric-card"><span class="eyebrow">System health</span><strong>99.9%</strong><small>All services nominal</small></article>
            <article class="metric-card accent-card"><span class="eyebrow">Uptime</span><strong>99.98%</strong><small>Target <b>99.95%</b></small><div class="progress"><span style="width:99.98%"></span></div></article>
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
                    <li><span class="activity-dot green"></span><span><strong>User created</strong><small>j.smith@sma.edu.ph · 15 min ago</small></span></li>
                    <li><span class="activity-dot blue"></span><span><strong>Settings updated</strong><small>Academic year config · 1 hr ago</small></span></li>
                    <li><span class="activity-dot amber"></span><span><strong>Backup completed</strong><small>Full database · 3 hrs ago</small></span></li>
                </ul>
            </article>
        </section>
    </main>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>