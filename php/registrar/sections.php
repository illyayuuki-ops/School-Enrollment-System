<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_role('registrar');
require_once dirname(__DIR__) . '/db_connect.php';

$pdo = db_connect();

// Group students by program and year_level as a stand-in for sections
$sections = [];
try {
    $stmt = $pdo->prepare("
        SELECT s.student_number, u.full_name, s.program, s.year_level, s.enrollment_status
        FROM students s
        JOIN users u ON s.user_id = u.id
        ORDER BY s.program, s.year_level, u.full_name
    ");
    $stmt->execute();
    $allStudents = $stmt->fetchAll();

    // Group by program + year_level
    foreach ($allStudents as $s) {
        $key = $s['program'] . ' - ' . $s['year_level'];
        $sections[$key][] = $s;
    }
} catch (PDOException $e) {
    // Empty on error
}

$pageTitle = 'Sectioning';
$extraStyles = ['/assets/registrar.css'];
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="app-frame">
    <?php require dirname(__DIR__) . '/includes/sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div><span class="eyebrow">Registrar workspace</span><h1>Good morning, <?= htmlspecialchars(explode(' ', $_SESSION['user']['full_name'])[0], ENT_QUOTES, 'UTF-8') ?>.</h1></div>
            <div class="topbar-actions"><span class="year-pill">A.Y. 2026–2027</span><a class="avatar" href="/logout.php" title="Sign out"><?= strtoupper(substr((string) $_SESSION['user']['full_name'], 0, 1)) ?></a></div>
        </header>
        <section class="content-grid">
            <article class="panel panel-wide">
                <div class="panel-heading"><div><span class="eyebrow">Records</span><h2>Sectioning</h2></div></div>

                <?php if (empty($sections)): ?>
                    <div class="empty-state">
                        <p>No student records found.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($sections as $sectionName => $students): ?>
                        <div class="section-group">
                            <h4><?= htmlspecialchars($sectionName) ?> <span style="font-weight: 400; color: var(--muted); font-size: 12px;">(<?= count($students) ?> students)</span></h4>
                            <div class="table-wrapper" style="overflow-x: auto;">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Student Number</th>
                                            <th>Name</th>
                                            <th>Enrollment Status</th>
                                            <th style="text-align: center;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($students as $s): ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars($s['student_number']) ?></strong></td>
                                                <td><?= htmlspecialchars($s['full_name']) ?></td>
                                                <td>
                                                    <span class="status-badge status-<?= $s['enrollment_status'] === 'enrolled' ? 'paid' : ($s['enrollment_status'] === 'pending' ? 'partial' : 'unpaid') ?>" style="font-size: 11px;">
                                                        <?= htmlspecialchars(ucfirst($s['enrollment_status'])) ?>
                                                    </span>
                                                </td>
                                                <td style="text-align: center;">
                                                    <a href="/registrar/payments.php?student_id=<?= urlencode($s['student_number']) ?>" class="button button-secondary" style="font-size: 11px; padding: 4px 10px;">View Balance</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </article>
        </section>
    </main>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>