<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_role('registrar');
require_once dirname(__DIR__) . '/db_connect.php';
require_once dirname(__DIR__) . '/includes/settings.php';

$pdo = db_connect();

// Enrolled students count
$enrolledStudents = 0;
$totalStudents = 0;
$outstandingStudents = 0;
$totalOutstanding = 0.00;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE enrollment_status = 'enrolled'");
    $stmt->execute();
    $enrolledStudents = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM students");
    $stmt->execute();
    $totalStudents = (int) $stmt->fetchColumn();

    // Students with outstanding balance
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT sl.student_id), COALESCE(SUM(sl.amount_due - sl.amount_paid), 0)
        FROM student_ledgers sl
        WHERE sl.amount_due > sl.amount_paid
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_NUM);
    $outstandingStudents = (int) ($result[0] ?? 0);
    $totalOutstanding = (float) ($result[1] ?? 0);
} catch (PDOException $e) {
    // Keep defaults on error
}

$pageTitle = 'Registrar Overview';
$extraStyles = ['/assets/registrar.css'];
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="app-frame">
    <?php require dirname(__DIR__) . '/includes/sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div><span class="eyebrow">Registrar workspace</span><h1>Welcome, <?= htmlspecialchars(explode(' ', $_SESSION['user']['full_name'])[0], ENT_QUOTES, 'UTF-8') ?>.</h1></div>
            <div class="topbar-actions"><span class="year-pill"><?= htmlspecialchars(get_academic_year($pdo)) ?></span><a class="avatar" href="#" onclick="return openLogoutModal()" title="Sign out"><?= strtoupper(substr((string) $_SESSION['user']['full_name'], 0, 1)) ?></a></div>
        </header>
        <section class="dashboard-grid">
            <article class="metric-card"><span class="eyebrow">Enrolled students</span><strong><?= number_format($enrolledStudents) ?></strong><small>Total: <?= number_format($totalStudents) ?></small></article>
            <article class="metric-card"><span class="eyebrow">Students with balance</span><strong><?= number_format($outstandingStudents) ?></strong><small>Of <?= number_format($totalStudents) ?> total</small></article>
            <article class="metric-card"><span class="eyebrow">Graduation candidates</span><strong>—</strong><small>data not available</small></article>
            <article class="metric-card accent-card"><span class="eyebrow">Total outstanding</span><strong>₱<?= number_format($totalOutstanding, 2) ?></strong></article>
        </section>
        <section class="content-grid">
            <article class="panel panel-wide">
                <div class="panel-heading"><div><span class="eyebrow">Enrollment & Balances</span><h2>Student Overview</h2></div></div>
                <?php
                $noBalance = max($totalStudents - $outstandingStudents, 0);
                $regData = [
                    ['label' => 'Enrolled', 'value' => $enrolledStudents, 'color' => '#3b82f6'],
                    ['label' => 'With Balance', 'value' => $outstandingStudents, 'color' => '#ef4444'],
                    ['label' => 'No Balance', 'value' => $noBalance, 'color' => '#22c55e'],
                ];
                $maxReg = max(array_column($regData, 'value'));
                if ($maxReg > 0): ?>
                    <div style="display:flex;align-items:flex-end;gap:16px;height:200px;padding:16px 0;">
                        <?php foreach ($regData as $item): ?>
                            <div style="display:flex;flex-direction:column;align-items:center;gap:8px;flex:1;height:100%;justify-content:flex-end;">
                                <div style="width:100%;height:<?= round(($item['value'] / $maxReg) * 100, 1) ?>%;background:<?= $item['color'] ?>;border-radius:4px 4px 0 0;min-height:4px;display:flex;align-items:center;justify-content:center;">
                                    <span style="color:#fff;font-size:12px;font-weight:700;"><?= number_format($item['value']) ?></span>
                                </div>
                                <span style="font-size:11px;color:var(--muted);text-align:center;"><?= htmlspecialchars($item['label']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </article>
        </section>
    </main>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>