<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_role('teacher');
require_once dirname(__DIR__) . '/db_connect.php';
require_once dirname(__DIR__) . '/includes/settings.php';

$pdo = db_connect();
$teacherId = (int) ($_SESSION['user']['id'] ?? 0);

$academicYear = get_academic_year($pdo);

$classes = [];
try {
    $stmt = $pdo->prepare('SELECT subject_code, subject_name, schedule_grid, room, capacity, enrolled_count, adviser_section FROM classes_and_schedules WHERE teacher_id = :tid ORDER BY subject_code');
    $stmt->execute(['tid' => $teacherId]);
    $classes = $stmt->fetchAll();
} catch (PDOException $e) {
    // Empty on error
}

$pageTitle = 'Active Classes';
$extraStyles = ['/assets/teacher.css'];
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="app-frame">
    <?php require dirname(__DIR__) . '/includes/sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div><span class="eyebrow">Faculty workspace</span><h1>Welcome, <?= htmlspecialchars(explode(' ', $_SESSION['user']['full_name'])[0], ENT_QUOTES, 'UTF-8') ?>.</h1></div>
            <div class="topbar-actions"><span class="year-pill"><?= htmlspecialchars(get_academic_year($pdo)) ?></span><a class="avatar" href="#" onclick="return openLogoutModal()" title="Sign out"><?= strtoupper(substr((string) $_SESSION['user']['full_name'], 0, 1)) ?></a></div>
        </header>
        <section class="content-grid">
            <article class="panel panel-wide">
                <div class="panel-heading"><div><span class="eyebrow">My Classes</span><h2>Active Classes</h2></div></div>
                <?php if (empty($classes)): ?>
                    <div class="empty-state">
                        <p>No classes assigned yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-wrapper" style="overflow-x: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Subject Code</th>
                                    <th>Subject Name</th>
                                    <th>Schedule</th>
                                    <th>Room</th>
                                    <th style="text-align: right;">Enrolled / Capacity</th>
                                    <th>Adviser</th>
                                    <th style="text-align: center;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($classes as $c): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($c['subject_code']) ?></strong></td>
                                        <td><?= htmlspecialchars($c['subject_name']) ?></td>
                                        <td style="font-family: monospace; font-size: 12px;"><?= htmlspecialchars($c['schedule_grid']) ?></td>
                                        <td><?= htmlspecialchars($c['room']) ?></td>
                                        <td style="text-align: right;"><?= (int) $c['enrolled_count'] ?> / <?= (int) $c['capacity'] ?></td>
                                        <td><?= $c['adviser_section'] ? '<span class="status-badge status-paid" style="font-size: 11px;">Adviser: ' . htmlspecialchars($c['adviser_section']) . '</span>' : '<span class="status-badge status-unpaid" style="font-size: 11px;">Not adviser</span>' ?></td>
                                        <td style="text-align: center;">
                                            <a href="/teacher/grades.php?subject=<?= urlencode($c['subject_code']) ?>" class="button button-secondary" style="font-size: 12px; padding: 6px 12px;">Grades</a>
                                            <a href="/teacher/attendance.php?subject=<?= urlencode($c['subject_code']) ?>" class="button button-secondary" style="font-size: 12px; padding: 6px 12px; margin-left: 8px;">Attendance</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </article>
        </section>
    </main>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>