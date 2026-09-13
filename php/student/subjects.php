<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_role('student');
require_once dirname(__DIR__) . '/db_connect.php';
require_once dirname(__DIR__) . '/includes/settings.php';

$pdo = db_connect();
$studentId = (int) ($_SESSION['user']['id'] ?? 0);

$academicYear = get_academic_year($pdo);

$student = null;
$plan = [];
try {
    $stmt = $pdo->prepare("SELECT id, student_number, program, year_level FROM students WHERE user_id = :uid");
    $stmt->execute(['uid' => $studentId]);
    $student = $stmt->fetch();

    if ($student) {
        $stmt = $pdo->prepare("
            SELECT * FROM plan_of_study_subjects
            WHERE student_id = :sid
            ORDER BY year_level, semester, display_order
        ");
        $stmt->execute(['sid' => $student['id']]);
        $plan = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    // Keep defaults on error
}

$pageTitle = 'Subjects / Plan of Study';
$extraStyles = ['/assets/student.css'];
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="app-frame">
    <?php require dirname(__DIR__) . '/includes/sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div><span class="eyebrow">Student workspace</span><h1>Welcome, <?= htmlspecialchars(explode(' ', $_SESSION['user']['full_name'])[0], ENT_QUOTES, 'UTF-8') ?>.</h1></div>
            <div class="topbar-actions"><span class="year-pill"><?= htmlspecialchars(get_academic_year($pdo)) ?></span><a class="avatar" href="#" onclick="return openLogoutModal()" title="Sign out"><?= strtoupper(substr((string) $_SESSION['user']['full_name'], 0, 1)) ?></a></div>
        </header>

        <section class="content-grid">
            <article class="panel panel-wide">
                <div class="panel-heading"><div><span class="eyebrow">Academics</span><h2>Plan of Study</h2></div></div>

                <?php if (empty($plan)): ?>
                    <div class="empty-state">
                        <p>No plan of study recorded yet.</p>
                    </div>
                <?php else: ?>
                    <?php
                    // Group by year_level then semester
                    $grouped = [];
                    foreach ($plan as $p) {
                        $yl = $p['year_level'] ?? 'Unknown';
                        $sem = $p['semester'] ?? 'Unknown';
                        $grouped[$yl][$sem][] = $p;
                    }
                    ksort($grouped);
                    foreach ($grouped as $yl => $semesters):
                    ?>
                        <div style="margin-top: 24px;">
                            <h3 style="font-size: 14px; font-weight: 700; color: var(--navy); margin-bottom: 16px; padding-bottom: 8px; border-bottom: 1px solid var(--line);"><?= htmlspecialchars($yl) ?></h3>
                            <?php foreach ($semesters as $sem => $subjects): ?>
                                <h4 style="font-size: 13px; font-weight: 600; color: var(--navy); margin: 16px 0 8px;"><?= htmlspecialchars($sem) ?></h4>
                                <div class="table-wrapper" style="overflow-x: auto;">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th>Subject Code</th>
                                                <th>Description</th>
                                                <th style="text-align: center;">Units</th>
                                                <th>Pre-Requisite</th>
                                                <th>Status</th>
                                                <th>Sem</th>
                                                <th>Curriculum Year</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($subjects as $s): ?>
                                                <tr>
                                                    <td><strong><?= htmlspecialchars($s['subject_code']) ?></strong></td>
                                                    <td><?= htmlspecialchars($s['description']) ?></td>
                                                    <td style="text-align: center;"><?= (int) $s['units'] ?></td>
                                                    <td><?= htmlspecialchars($s['prerequisite'] ?? '—') ?></td>
                                                    <td>
                                                        <span class="status-badge status-<?= $s['status'] === 'completed' ? 'paid' : ($s['status'] === 'in_progress' ? 'partial' : 'unpaid') ?>" style="font-size: 11px; text-transform: capitalize;">
                                                            <?= htmlspecialchars(ucfirst($s['status'] ?? 'planned')) ?>
                                                        </span>
                                                    </td>
                                                    <td style="text-align: center;"><?= htmlspecialchars($s['semester']) ?></td>
                                                    <td><?= htmlspecialchars($s['curriculum_year'] ?? '—') ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </article>
        </section>
    </main>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>