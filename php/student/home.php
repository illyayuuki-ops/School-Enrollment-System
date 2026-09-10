<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_role('student');
$pageTitle = 'Student Overview';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="app-frame">
    <?php require dirname(__DIR__) . '/includes/sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div><span class="eyebrow">Student workspace</span><h1>Good morning, <?= htmlspecialchars(explode(' ', $_SESSION['user']['full_name'])[0], ENT_QUOTES, 'UTF-8') ?>.</h1></div>
            <div class="topbar-actions"><span class="year-pill">A.Y. 2026–2027</span><a class="avatar" href="/logout.php" title="Sign out"><?= strtoupper(substr((string) $_SESSION['user']['full_name'], 0, 1)) ?></a></div>
        </header>
        <section class="dashboard-grid">
            <article class="metric-card"><span class="eyebrow">Enrolled courses</span><strong>5</strong><small><b class="trend-up">+1</b> this semester</small></article>
            <article class="metric-card"><span class="eyebrow">Pending tasks</span><strong>3</strong><small><b class="trend-warn">2 due this week</b></small></article>
            <article class="metric-card"><span class="eyebrow">GPA</span><strong>3.72</strong><small>Target <b>3.80</b></small></article>
            <article class="metric-card accent-card"><span class="eyebrow">Attendance</span><strong>94%</strong><small>Target <b>95%</b></small><div class="progress"><span style="width:94%"></span></div></article>
        </section>
        <section class="content-grid">
            <article class="panel panel-wide">
                <div class="panel-heading"><div><span class="eyebrow">Quick actions</span><h2>Stay on track</h2></div></div>
                <div class="action-list">
                    <a href="/student/enrollment.php"><span class="action-icon blue">01</span><span><strong>Enrollment wizard</strong><small>Select courses for next term</small></span><span class="arrow">↗</span></a>
                    <a href="/student/load.php"><span class="action-icon green">02</span><span><strong>Study load</strong><small>View current schedule and units</small></span><span class="arrow">↗</span></a>
                    <a href="/student/status.php"><span class="action-icon amber">03</span><span><strong>Application status</strong><small>Check pending requests</small></span><span class="arrow">↗</span></a>
                </div>
            </article>
            <article class="panel">
                <div class="panel-heading"><div><span class="eyebrow">Recent activity</span><h2>What's new</h2></div></div>
                <ul class="activity-list">
                    <li><span class="activity-dot green"></span><span><strong>Course enrolled</strong><small>CS101 · 2 hrs ago</small></span></li>
                    <li><span class="activity-dot blue"></span><span><strong>Grade posted</strong><small>MATH201 · 1 day ago</small></span></li>
                    <li><span class="activity-dot amber"></span><span><strong>Payment due</strong><small>Tuition · 3 days ago</small></span></li>
                </ul>
            </article>
        </section>
    </main>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>