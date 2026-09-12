<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_role('registrar');
require_once dirname(__DIR__) . '/db_connect.php';

$pdo = db_connect();

$classes = [];
try {
    $stmt = $pdo->prepare("
        SELECT c.subject_code, c.subject_name, u.full_name as teacher_name, c.schedule_grid, c.room, c.capacity, c.enrolled_count
        FROM classes_and_schedules c
        LEFT JOIN users u ON c.teacher_id = u.id
        ORDER BY c.subject_code
    ");
    $stmt->execute();
    $classes = $stmt->fetchAll();
} catch (PDOException $e) {
    // Empty on error
}

$pageTitle = 'Subject Allocation';
$extraStyles = ['/assets/registrar.css'];
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="app-frame">
    <?php require dirname(__DIR__) . '/includes/sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div><span class="eyebrow">Registrar workspace</span><h1>Welcome, <?= htmlspecialchars(explode(' ', $_SESSION['user']['full_name'])[0], ENT_QUOTES, 'UTF-8') ?>.</h1></div>
            <div class="topbar-actions"><span class="year-pill">A.Y. 2026–2027</span><a class="avatar" href="#" onclick="return openLogoutModal()" title="Sign out"><?= strtoupper(substr((string) $_SESSION['user']['full_name'], 0, 1)) ?></a></div>
        </header>
        <section class="content-grid">
            <article class="panel panel-wide">
                <div class="panel-heading"><div><span class="eyebrow">Records</span><h2>Subject Allocation</h2></div></div>

                <?php if (empty($classes)): ?>
                    <div class="empty-state">
                        <p>No subjects allocated yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-wrapper" style="overflow-x: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Subject Code</th>
                                    <th>Subject Name</th>
                                    <th>Teacher</th>
                                    <th>Schedule</th>
                                    <th>Room</th>
                                    <th style="text-align: right;">Enrolled / Capacity</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($classes as $c): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($c['subject_code']) ?></strong></td>
                                        <td><?= htmlspecialchars($c['subject_name']) ?></td>
                                        <td><?= htmlspecialchars($c['teacher_name'] ?? 'Unassigned') ?></td>
                                        <td style="font-family: monospace; font-size: 12px;"><?= htmlspecialchars($c['schedule_grid']) ?></td>
                                        <td><?= htmlspecialchars($c['room']) ?></td>
                                        <td style="text-align: right;"><?= (int) $c['enrolled_count'] ?> / <?= (int) $c['capacity'] ?></td>
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