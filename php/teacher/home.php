<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_role('teacher');
$pageTitle = 'Faculty Overview';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="app-frame">
    <?php require dirname(__DIR__) . '/includes/sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div><span class="eyebrow">Faculty workspace</span><h1>Good morning, <?= htmlspecialchars(explode(' ', $_SESSION['user']['full_name'])[0], ENT_QUOTES, 'UTF-8') ?>.</h1></div>
            <div class="topbar-actions"><span class="year-pill">A.Y. 2026–2027</span><a class="avatar" href="/logout.php" title="Sign out"><?= strtoupper(substr((string) $_SESSION['user']['full_name'], 0, 1)) ?></a></div>
        </header>
        <section class="dashboard-grid">
            <article class="metric-card"><span class="eyebrow">Active classes</span><strong>4</strong><small><b class="trend-up">+1</b> this term</small></article>
            <article class="metric-card"><span class="eyebrow">Total students</span><strong>128</strong><small><b class="trend-up">+14</b> vs last term</small></article>
            <article class="metric-card"><span class="eyebrow">Pending grades</span><strong>23</strong><small><b class="trend-warn">8 due this week</b></small></article>
            <article class="metric-card accent-card"><span class="eyebrow">Completion rate</span><strong>87%</strong><small>Target <b>95%</b></small><div class="progress"><span style="width:87%"></span></div></article>
        </section>
        <section class="content-grid">
            <article class="panel panel-wide">
                <div class="panel-heading"><div><span class="eyebrow">Quick actions</span><h2>Teaching tools</h2></div></div>
                <div class="action-list">
                    <a href="/teacher/classes.php"><span class="action-icon blue">01</span><span><strong>Active classes</strong><small>Manage rosters and schedules</small></span><span class="arrow">↗</span></a>
                    <a href="/teacher/grades.php"><span class="action-icon green">02</span><span><strong>Grade encoding</strong><small>Enter and submit grades</small></span><span class="arrow">↗</span></a>
                    <a href="/teacher/schedule.php"><span class="action-icon amber">03</span><span><strong>Schedule matrix</strong><small>View weekly timetable</small></span><span class="arrow">↗</span></a>
                </div>
            </article>
            <article class="panel">
                <div class="panel-heading"><div><span class="eyebrow">Recent activity</span><h2>Class updates</h2></div></div>
                <ul class="activity-list">
                    <li><span class="activity-dot green"></span><span><strong>Grades submitted</strong><small>CS101 Midterm · 2 hrs ago</small></span></li>
                    <li><span class="activity-dot blue"></span><span><strong>Attendance recorded</strong><small>MATH201 Lec · 4 hrs ago</small></span></li>
                    <li><span class="activity-dot amber"></span><span><strong>Assignment posted</strong><small>PHYS101 HW3 · 1 day ago</small></span></li>
                </ul>
            </article>
        </section>
    </main>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>