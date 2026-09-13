<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_role('student');
require_once dirname(__DIR__) . '/db_connect.php';
require_once dirname(__DIR__) . '/includes/settings.php';

$pdo = db_connect();
$studentId = (int) ($_SESSION['user']['id'] ?? 0);

$academicYear = get_academic_year($pdo);

// Get student record
$student = null;
$ledger = [];
try {
    $stmt = $pdo->prepare("SELECT id, student_number, program, year_level FROM students WHERE user_id = :uid");
    $stmt->execute(['uid' => $studentId]);
    $student = $stmt->fetch();

    if ($student) {
        // Get ledger grouped by academic year
        $stmt = $pdo->prepare("
            SELECT id, academic_year, description, amount_due, amount_paid, status, created_at
            FROM student_ledgers
            WHERE student_id = :sid
            ORDER BY academic_year DESC, created_at DESC
        ");
        $stmt->execute(['sid' => $student['id']]);
        $ledger = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    // Keep defaults on error
}

$pageTitle = 'Statement of Account';
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
                <div class="panel-heading"><div><span class="eyebrow">Financial</span><h2>Statement of Account</h2></div></div>

                <?php if ($student): ?>
                    <!-- Balance Summary -->
                    <div class="student-balance-summary" style="margin-bottom: 24px;">
                        <div class="summary-title">Account Balance (<?= htmlspecialchars(get_academic_year($pdo)) ?>)</div>
                        <?php
                        $totalDue = array_sum(array_column($ledger, 'amount_due'));
                        $totalPaid = array_sum(array_column($ledger, 'amount_paid'));
                        $balance = $totalDue - $totalPaid;
                        ?>
                        <div class="summary-row">
                            <span class="summary-label">Total Assessed</span>
                            <span class="summary-value due">₱<?= number_format($totalDue, 2) ?></span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Total Paid</span>
                            <span class="summary-value paid">₱<?= number_format($totalPaid, 2) ?></span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Outstanding Balance</span>
                            <span class="summary-value balance <?= ($totalDue - $totalPaid) > 0 ? 'due' : '' ?>">₱<?= number_format($totalDue - $totalPaid, 2) ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (empty($ledger)): ?>
                    <div class="empty-state">
                        <p>No payment records found.</p>
                    </div>
                <?php else: ?>
                    <!-- Group by academic year -->
                    <?php
                    $grouped = [];
                    foreach ($ledger as $entry) {
                        $ay = $entry['academic_year'] ?? 'Unknown';
                        $grouped[$ay][] = $entry;
                    }
                    krsort($grouped);
                    foreach ($grouped as $ay => $entries):
                        $ayDue = array_sum(array_column($entries, 'amount_due'));
                        $ayPaid = array_sum(array_column($entries, 'amount_paid'));
                        $ayBalance = $ayDue - $ayPaid;
                    ?>
                        <div class="detail-section" style="margin-top: 24px;">
                            <h4>Academic Year: <?= htmlspecialchars($ay) ?></h4>
                            <div class="balance-cards" style="margin-bottom: 16px;">
                                <div class="balance-card">
                                    <div class="card-label">Total Assessed</div>
                                    <div class="card-value">₱<?= number_format(array_sum(array_column($entries, 'amount_due')), 2) ?></div>
                                </div>
                                <div class="balance-card">
                                    <div class="card-label">Total Paid</div>
                                    <div class="card-value positive">₱<?= number_format(array_sum(array_column($entries, 'amount_paid')), 2) ?></div>
                                </div>
                                <div class="balance-card">
                                    <div class="card-label">Balance</div>
                                    <div class="card-value balance <?= (array_sum(array_column($entries, 'amount_due')) - array_sum(array_column($entries, 'amount_paid'))) > 0 ? 'due' : '' ?>">₱<?= number_format(array_sum(array_column($entries, 'amount_due')) - array_sum(array_column($entries, 'amount_paid')), 2) ?></div>
                                </div>
                            </div>

                            <div class="table-wrapper" style="overflow-x: auto;">
                                <table class="ledger-table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Description</th>
                                            <th style="text-align: right;">Amount Due</th>
                                            <th style="text-align: right;">Amount Paid</th>
                                            <th style="text-align: right;">Balance</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($entries as $entry): ?>
                                            <?php
                                            $bal = (float)$entry['amount_due'] - (float)$entry['amount_paid'];
                                            $statusClass = $entry['status'] === 'paid' ? 'status-paid' : ($entry['status'] === 'partial' ? 'status-partial' : 'status-unpaid');
                                            ?>
                                            <tr class="ledger-row ledger-status-<?= htmlspecialchars($entry['status']) ?>">
                                                <td><?= date('M d, Y', strtotime($entry['created_at'] ?? 'now')) ?></td>
                                                <td><?= htmlspecialchars($entry['description']) ?></td>
                                                <td class="amount amount-due" style="text-align: right;">₱<?= number_format((float)$entry['amount_due'], 2) ?></td>
                                                <td class="amount amount-paid" style="text-align: right;">₱<?= number_format((float)$entry['amount_paid'], 2) ?></td>
                                                <td class="balance" style="text-align: right;">₱<?= number_format((float)$entry['amount_due'] - (float)$entry['amount_paid'], 2) ?></td>
                                                <td>
                                                    <span class="status-badge status-<?= htmlspecialchars($entry['status']) ?>">
                                                        <?= htmlspecialchars(ucfirst($entry['status'])) ?>
                                                    </span>
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