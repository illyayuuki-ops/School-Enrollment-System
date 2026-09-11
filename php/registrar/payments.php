<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_role('registrar');
require_once dirname(__DIR__) . '/db_connect.php';

$pdo = db_connect();

$search = trim($_GET['search'] ?? '');
$selectedStudent = $_GET['student_id'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Get all students with their outstanding balances
$studentsWithBalance = [];
$totalStudents = 0;
$totalOutstanding = 0.00;
try {
    $where = '';
    $params = [];
    if ($search !== '') {
        $where = 'WHERE s.student_number ILIKE :search OR u.full_name ILIKE :search';
        $params['search'] = "%{$search}%";
    }

    // Total count
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT s.id)
        FROM students s
        JOIN users u ON s.user_id = u.id
        LEFT JOIN (
            SELECT student_id, SUM(amount_due - amount_paid) AS balance
            FROM student_ledgers
            GROUP BY student_id
        ) sl ON s.id = sl.student_id
        {$where}
    ");
    $stmt->execute($params);
    $totalStudents = (int) $stmt->fetchColumn();

    // Fetch students with balances
    $stmt = $pdo->prepare("
        SELECT s.student_number, u.full_name, s.program, s.year_level,
               COALESCE(sl.balance, 0) AS outstanding_balance
        FROM students s
        JOIN users u ON s.user_id = u.id
        LEFT JOIN (
            SELECT student_id, SUM(amount_due - amount_paid) AS balance
            FROM student_ledgers
            GROUP BY student_id
        ) sl ON s.id = sl.student_id
        {$where}
        ORDER BY sl.balance DESC NULLS LAST, s.student_number
        LIMIT :limit OFFSET :offset
    ");
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
    $stmt->execute();
    $studentsWithBalance = $stmt->fetchAll();

    // Total outstanding across all students
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount_due - amount_paid), 0) FROM student_ledgers");
    $totalOutstanding = (float) $stmt->fetchColumn();

    // Count students with outstanding balance
    $stmt = $pdo->query("SELECT COUNT(DISTINCT student_id) FROM student_ledgers WHERE amount_due > amount_paid");
    $studentsWithBalanceCount = (int) $stmt->fetchColumn();

} catch (PDOException $e) {
    // Keep defaults on error
}

// Get ledger for selected student
$ledger = [];
$studentInfo = null;
if ($selectedStudent) {
    try {
        $stmt = $pdo->prepare("
            SELECT s.student_number, u.full_name, s.program, s.year_level
            FROM students s
            JOIN users u ON s.user_id = u.id
            WHERE s.student_number = :sn
        ");
        $stmt->execute(['sn' => $selectedStudent]);
        $studentInfo = $stmt->fetch();

        if ($studentInfo) {
            $stmt = $pdo->prepare("
                SELECT id, academic_year, description, amount_due, amount_paid, status, created_at
                FROM student_ledgers
                WHERE student_id = (SELECT id FROM students WHERE student_number = :sn)
                ORDER BY created_at DESC
            ");
            $stmt->execute(['sn' => $selectedStudent]);
            $ledger = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        // Empty on error
    }
}

$totalPages = max(1, (int) ceil($totalStudents / $perPage));
if ($page > $totalPages) $page = $totalPages;

$pageTitle = 'Payments / Assessment';
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
                <div class="panel-heading"><div><span class="eyebrow">Financial</span><h2>Payments / Assessment</h2></div></div>

                <!-- Summary Cards -->
                <div class="balance-cards">
                    <div class="balance-card">
                        <div class="card-label">Total Assessed</div>
                        <div class="card-value">₱<?= number_format($totalOutstanding + array_sum(array_column($ledger, 'amount_paid')), 2) ?></div>
                        <div class="card-sub">Total amount due across all students</div>
                    </div>
                    <div class="balance-card">
                        <div class="card-label">Total Paid</div>
                        <div class="card-value positive">₱<?= number_format(array_sum(array_column($ledger, 'amount_paid')), 2) ?></div>
                        <div class="card-sub">Total payments received</div>
                    </div>
                    <div class="balance-card">
                        <div class="card-label">Outstanding Balance</div>
                        <div class="card-value negative">₱<?= number_format($totalOutstanding, 2) ?></div>
                        <div class="card-sub">Total amount still owed</div>
                    </div>
                    <div class="balance-card">
                        <div class="card-label">Students with Balance</div>
                        <div class="card-value"><?= number_format($studentsWithBalanceCount) ?></div>
                        <div class="card-sub">Of <?= number_format($totalStudents) ?> total students</div>
                    </div>
                </div>

                <!-- Student List with Search -->
                <div class="search-bar">
                    <form method="get" action="/registrar/payments.php" class="form-field" style="flex: 1; min-width: 250px;">
                        <label for="search" class="eyebrow">Search Students</label>
                        <input type="search" id="search" name="search" placeholder="Student number or name..." value="<?= htmlspecialchars($search) ?>" style="height: 40px;">
                    </form>
                    <span style="color: var(--muted); font-size: 13px;"><?= number_format($totalStudents) ?> student<?= $totalStudents !== 1 ? 's' : '' ?> found</span>
                </form>

                <!-- Student List Table -->
                <div class="table-wrapper" style="overflow-x: auto;">
                    <table class="student-balance-list">
                        <thead>
                            <tr>
                                <th>Student Number</th>
                                <th>Name</th>
                                <th>Program</th>
                                <th>Year Level</th>
                                <th style="text-align: right;">Outstanding Balance</th>
                                <th style="text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($studentsWithBalance)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 40px; color: var(--muted);">No students found<?= $search ? ' matching "' . htmlspecialchars($search) . '"' : '' ?>.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($studentsWithBalance as $s): ?>
                                    <tr class="<?= $selectedStudent === $s['student_number'] ? 'selected' : '' ?>">
                                        <td><strong><?= htmlspecialchars($s['student_number']) ?></strong></td>
                                        <td><?= htmlspecialchars($s['full_name']) ?></td>
                                        <td><?= htmlspecialchars($s['program']) ?></td>
                                        <td><?= htmlspecialchars($s['year_level']) ?></td>
                                        <td class="balance <?= (float)$s['outstanding_balance'] > 0 ? 'positive' : 'zero' ?>" style="text-align: right;">
                                            ₱<?= number_format((float)$s['outstanding_balance'], 2) ?>
                                        </td>
                                        <td style="text-align: center;">
                                            <a href="/registrar/payments.php?student_id=<?= urlencode($s['student_number']) ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $page > 1 ? '&page=' . $page : '' ?>" class="button button-secondary" style="font-size: 11px; padding: 4px 10px;">View Ledger</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPages > 1): ?>
                    <div style="display: flex; justify-content: center; gap: 8px; margin-top: 16px;">
                        <?php if ($page > 1): ?>
                            <a href="/registrar/payments.php?page=<?= $page - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>" class="button button-secondary" style="font-size: 12px;">← Previous</a>
                        <?php endif; ?>
                        <span style="display: flex; align-items: center; color: var(--muted);">Page <?= $page ?> of <?= $totalPages ?></span>
                        <?php if ($page < $totalPages): ?>
                            <a href="/registrar/payments.php?page=<?= $page + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>" class="button button-secondary" style="font-size: 12px;">Next →</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Selected Student Ledger -->
                <?php if ($studentInfo): ?>
                    <div style="margin-top: 32px;">
                        <div class="balance-cards" style="margin-bottom: 16px;">
                            <div class="balance-card">
                                <div class="card-label">Student</div>
                                <div class="card-value" style="font-size: 18px;"><?= htmlspecialchars($studentInfo['full_name']) ?></div>
                                <div class="card-sub"><?= htmlspecialchars($studentInfo['student_number']) ?> · <?= htmlspecialchars($studentInfo['program']) ?> · <?= htmlspecialchars($studentInfo['year_level']) ?></div>
                            </div>
                            <?php
                            $totalDue = array_sum(array_column($ledger, 'amount_due'));
                            $totalPaid = array_sum(array_column($ledger, 'amount_paid'));
                            $balance = $totalDue - $totalPaid;
                            ?>
                            <div class="balance-card">
                                <div class="card-label">Total Assessed</div>
                                <div class="card-value">₱<?= number_format($totalDue, 2) ?></div>
                            </div>
                            <div class="balance-card">
                                <div class="card-label">Total Paid</div>
                                <div class="card-value positive">₱<?= number_format($totalPaid, 2) ?></div>
                            </div>
                            <div class="balance-card">
                                <div class="card-label">Outstanding</div>
                                <div class="card-value <?= $balance > 0 ? 'negative' : 'positive' ?>">₱<?= number_format($balance, 2) ?></div>
                            </div>
                        </div>

                        <!-- Ledger Table -->
                        <div class="table-wrapper" style="overflow-x: auto;">
                            <table class="ledger-table">
                                <thead>
                                    <tr>
                                        <th>Academic Year</th>
                                        <th>Description</th>
                                        <th style="text-align: right;">Amount Due</th>
                                        <th style="text-align: right;">Amount Paid</th>
                                        <th style="text-align: right;">Balance</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($ledger)): ?>
                                        <tr>
                                            <td colspan="6" style="text-align: center; padding: 40px; color: var(--muted);">No ledger entries for this student.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($ledger as $l): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($l['academic_year']) ?></td>
                                                <td><?= htmlspecialchars($l['description']) ?></td>
                                                <td class="amount amount-due">₱<?= number_format((float)$l['amount_due'], 2) ?></td>
                                                <td class="amount amount-paid">₱<?= number_format((float)$l['amount_paid'], 2) ?></td>
                                                <td class="balance" style="text-align: right;">₱<?= number_format((float)$l['amount_due'] - (float)$l['amount_paid'], 2) ?></td>
                                                <td>
                                                    <span class="status-badge status-<?= htmlspecialchars($l['status']) ?>">
                                                        <?= htmlspecialchars(ucfirst($l['status'])) ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </article>
        </section>
    </main>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>