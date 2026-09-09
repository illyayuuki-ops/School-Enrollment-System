<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_auth();
$pageTitle = 'Overview';
require __DIR__ . '/includes/header.php';
?>
<div class="app-frame">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div><span class="eyebrow">Workspace overview</span><h1>Good morning, <?= htmlspecialchars(explode(' ', $_SESSION['user']['full_name'])[0], ENT_QUOTES, 'UTF-8') ?>.</h1></div>
            <div class="topbar-actions"><span class="year-pill">A.Y. 2026–2027</span><a class="avatar" href="/logout.php" title="Sign out"><?= strtoupper(substr((string) $_SESSION['user']['full_name'], 0, 1)) ?></a></div>
        </header>
        <section class="dashboard-grid">
            <article class="metric-card"><span class="eyebrow">Applicants</span><strong>1,284</strong><small><b class="trend-up">+8.4%</b> vs last month</small></article>
            <article class="metric-card"><span class="eyebrow">Pending review</span><strong>86</strong><small><b class="trend-warn">12 due today</b> need attention</small></article>
            <article class="metric-card"><span class="eyebrow">Enrolled students</span><strong>4,692</strong><small><b class="trend-up">+14.2%</b> year over year</small></article>
            <article class="metric-card accent-card"><span class="eyebrow">Completion rate</span><strong>78.6%</strong><small>Target <b>85%</b> by Aug 30</small><div class="progress"><span style="width:78.6%"></span></div></article>
        </section>
        <section class="content-grid">
            <article class="panel panel-wide"><div class="panel-heading"><div><span class="eyebrow">Action center</span><h2>Keep enrollment moving</h2></div><a href="/admission/queue.php" class="text-link">View queue →</a></div><div class="action-list"><a href="/admission/queue.php"><span class="action-icon blue">01</span><span><strong>Review applicant documents</strong><small>86 applicants are waiting for verification.</small></span><span class="arrow">↗</span></a><a href="/registrar/students.php"><span class="action-icon green">02</span><span><strong>Confirm section assignments</strong><small>18 students need a final section.</small></span><span class="arrow">↗</span></a><a href="/student/status.php"><span class="action-icon amber">03</span><span><strong>Monitor enrollment completion</strong><small>32 applications have missing requirements.</small></span><span class="arrow">↗</span></a></div></article>
            <article class="panel"><div class="panel-heading"><div><span class="eyebrow">Recent activity</span><h2>What changed</h2></div></div><ul class="activity-list"><li><span class="activity-dot green"></span><span><strong>Application verified</strong><small>Maria Santos · 8 min ago</small></span></li><li><span class="activity-dot blue"></span><span><strong>New application received</strong><small>Daniel Tan · 24 min ago</small></span></li><li><span class="activity-dot amber"></span><span><strong>Document returned</strong><small>Leah Wong · 1 hr ago</small></span></li><li><span class="activity-dot slate"></span><span><strong>Section updated</strong><small>BSCS 2A · 2 hrs ago</small></span></li></ul></article>
        </section>
    </main>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>