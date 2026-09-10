<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_role('registrar');
$pageTitle = 'Registrar Overview';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="app-frame">
    <?php require dirname(__DIR__) . '/includes/sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div><span class="eyebrow">Registrar workspace</span><h1>Good morning, <?= htmlspecialchars(explode(' ', $_SESSION['user']['full_name'])[0], ENT_QUOTES, 'UTF-8') ?>.</h1></div>
            <div class="topbar-actions"><span class="year-pill">A.Y. 2026–2027</span><a class="avatar" href="/logout.php" title="Sign out"><?= strtoupper(substr((string) $_SESSION['user']['full_name'], 0, 1)) ?></a></div>
        </header>
        <section class="dashboard-grid">
            <article class="metric-card"><span class="eyebrow">Enrolled students</span><strong>4,692</strong><small><b class="trend-up">+14.2%</b> year over year</small></article>
            <article class="metric-card"><span class="eyebrow">Pending sections</span><strong>18</strong><small><b class="trend-warn">5 need review</b></small></article>
            <article class="metric-card"><span class="eyebrow">Graduation candidates</span><strong>892</strong><small>On track for May</small></article>
            <article class="metric-card accent-card"><span class="eyebrow">Record accuracy</span><strong>99.4%</strong><small>Target <b>99.5%</b></small><div class="progress"><span style="width:99.4%"></span></div></article>
        </section>
        <section class="content-grid">
            <article class="panel panel-wide">
                <div class="panel-heading"><div><span class="eyebrow">Quick actions</span><h2>Records management</h2></div></div>
                <div class="action-list">
                    <a href="/registrar/students.php"><span class="action-icon blue">01</span><span><strong>Student masterlist</strong><small>Search and manage records</small></span><span class="arrow">↗</span></a>
                    <a href="/registrar/sections.php"><span class="action-icon green">02</span><span><strong>Sectioning</strong><small>Assign students to sections</small></span><span class="arrow">↗</span></a>
                    <a href="/registrar/subjects.php"><span class="action-icon amber">03</span><span><strong>Subject allocation</strong><small>Manage course offerings</small></span><span class="arrow">↗</span></a>
                </div>
            </article>
            <article class="panel">
                <div class="panel-heading"><div><span class="eyebrow">Recent activity</span><h2>Record changes</h2></div></div>
                <ul class="activity-list">
                    <li><span class="activity-dot green"></span><span><strong>Section assigned</strong><small>BSCS 2A · 30 min ago</small></span></li>
                    <li><span class="activity-dot blue"></span><span><strong>Record updated</strong><small>J. Dela Cruz · 2 hrs ago</small></span></li>
                    <li><span class="activity-dot amber"></span><span><strong>Graduation audit</strong><small>Batch 2024 · 1 day ago</small></span></li>
                </ul>
            </article>
        </section>
    </main>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>