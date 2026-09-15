<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_role('admin');
require_once dirname(__DIR__) . '/db_connect.php';
require_once dirname(__DIR__) . '/includes/settings.php';

$pdo = db_connect();

$search = trim($_GET['search'] ?? '');
$selectedStudent = $_GET['student_id'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = '';
$params = [];

if ($search !== '') {
    $where = 'WHERE s.student_number ILIKE :search OR u.full_name ILIKE :search OR u.email ILIKE :search';
    $params['search'] = "%{$search}%";
}

// Total count
$total = 0;
try {
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
    $total = (int) $stmt->fetchColumn();
} catch (PDOException $e) {
    $total = 0;
}
$totalPages = max(1, (int) ceil($total / $perPage));
if ($page > $totalPages) $page = $totalPages;

// Fetch students with balances
$studentsWithBalance = [];
try {
    $stmt = $pdo->prepare("
        SELECT s.student_number, u.full_name, s.program, s.year_level, u.email,
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
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $studentsWithBalance = $stmt->fetchAll();
} catch (PDOException $e) {
    // Keep defaults on error
}

// Get ledger for selected student
$ledger = [];
$studentInfo = null;
if ($selectedStudent) {
    try {
        $stmt = $pdo->prepare("
            SELECT s.id as student_id, s.student_number, u.full_name, s.program, s.year_level, u.email
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
                WHERE student_id = :sid
                ORDER BY academic_year DESC, created_at DESC
            ");
            $stmt->execute(['sid' => $studentInfo['id']]);
            $ledger = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        // Empty on error
    }
}

$totalPages = max(1, (int) ceil($total / $perPage));
if ($page > $totalPages) $page = $totalPages;

$pageTitle = 'Payments / Assessment';
$extraStyles = ['/assets/admin.css'];
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="app-frame">
    <?php require dirname(__DIR__) . '/includes/sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div><span class="eyebrow">Administrator workspace</span><h1>Welcome, <?= htmlspecialchars(explode(' ', $_SESSION['user']['full_name'])[0], ENT_QUOTES, 'UTF-8') ?>.</h1></div>
            <div class="topbar-actions"><span class="year-pill"><?= htmlspecialchars(get_academic_year($pdo)) ?></span><a class="avatar" href="#" onclick="return openLogoutModal()" title="Sign out"><?= strtoupper(substr((string) $_SESSION['user']['full_name'], 0, 1)) ?></a></div>
        </header>
        <section class="content-grid">
            <article class="panel panel-wide">
                <div class="panel-heading"><div><span class="eyebrow">Financial</span><h2>Payments / Assessment</h2></div></div>

                <!-- Search -->
                <div class="search-bar">
                    <form method="get" action="/admin/payments.php" class="form-field" style="flex: 1; min-width: 250px;">
                        <label for="search" class="eyebrow">Search Student</label>
                        <input type="search" id="search" name="search" placeholder="Student number, name, or email..." value="<?= htmlspecialchars($search) ?>" style="height: 40px;">
                    </form>
                    <span style="color: var(--muted); font-size: 13px;"><?= number_format($total) ?> student<?= $total !== 1 ? 's' : '' ?></span>
                </div>

                <!-- Student List Table -->
                <div class="table-wrapper" style="overflow-x: auto;">
                    <table class="student-balance-list">
                        <thead>
                            <tr>
                                <th>Student Number</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Program</th>
                                <th>Year Level</th>
                                <th style="text-align: right;">Outstanding Balance</th>
                                <th style="text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($studentsWithBalance)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 40px; color: var(--muted);">No students found<?= $search ? ' matching "' . htmlspecialchars($search) . '"' : '' ?>.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($studentsWithBalance as $s): ?>
                                    <tr class="<?= $selectedStudent === $s['student_number'] ? 'selected' : '' ?>">
                                        <td><strong><?= htmlspecialchars($s['student_number']) ?></strong></td>
                                        <td><?= htmlspecialchars($s['full_name']) ?></td>
                                        <td><?= htmlspecialchars($s['email']) ?></td>
                                        <td><?= htmlspecialchars($s['program']) ?></td>
                                        <td><?= htmlspecialchars($s['year_level']) ?></td>
                                        <td class="balance <?= (float)$s['outstanding_balance'] > 0 ? 'negative' : 'zero' ?>" style="text-align: right;">
                                            ₱<?= number_format((float)$s['outstanding_balance'], 2) ?>
                                        </td>
                                        <td style="text-align: center;">
                                            <a href="/admin/payments.php?student_id=<?= urlencode($s['student_number']) ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $page > 1 ? '&page=' . $page : '' ?>" class="button button-secondary" style="font-size: 11px; padding: 4px 10px;">View Ledger</a>
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
                            <a href="/admin/payments.php?page=<?= $page - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>" class="button button-secondary" style="font-size: 12px;">← Previous</a>
                        <?php endif; ?>
                        <span style="display: flex; align-items: center; color: var(--muted);">Page <?= $page ?> of <?= $totalPages ?></span>
                        <?php if ($page < $totalPages): ?>
                            <a href="/admin/payments.php?page=<?= $page + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>" class="button button-secondary" style="font-size: 12px;">Next →</a>
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
                                <div class="card-value negative">₱<?= number_format($balance, 2) ?></div>
                            </div>
                        </div>

                        <!-- Ledger Table with CRUD -->
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
                                        <th style="text-align: center;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($ledger)): ?>
                                        <tr>
                                            <td colspan="7" style="text-align: center; padding: 40px; color: var(--muted);">No ledger entries for this student.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($ledger as $l): ?>
                                            <tr class="ledger-row ledger-status-<?= htmlspecialchars($l['status']) ?>">
                                                <td><?= htmlspecialchars($l['academic_year']) ?></td>
                                                <td><?= htmlspecialchars($l['description']) ?></td>
                                                <td class="amount amount-due" style="text-align: right;">₱<?= number_format((float)$l['amount_due'], 2) ?></td>
                                                <td class="amount amount-paid" style="text-align: right;">₱<?= number_format((float)$l['amount_paid'], 2) ?></td>
                                                <td class="balance" style="text-align: right;">₱<?= number_format((float)$l['amount_due'] - (float)$l['amount_paid'], 2) ?></td>
                                                <td>
                                                    <span class="status-badge status-<?= htmlspecialchars($l['status']) ?>">
                                                        <?= htmlspecialchars(ucfirst($l['status'])) ?>
                                                    </span>
                                                </td>
                                                <td style="text-align: center;">
                                                    <div class="action-buttons">
                                                        <button type="button" class="btn-edit" onclick="openEditLedgerModal(<?= (int) $l['id'] ?>, '<?= htmlspecialchars($l['academic_year'], ENT_QUOTES) ?>', '<?= htmlspecialchars($l['description'], ENT_QUOTES) ?>', '<?= (float)$l['amount_due'] ?>', '<?= (float)$l['amount_paid'] ?>', '<?= htmlspecialchars($l['status'], ENT_QUOTES) ?>')" style="padding: 4px 10px; font-size: 11px;">Edit</button>
                                                        <form method="post" action="/admin/process_payment.php" style="display: inline;" onsubmit="return confirm('Delete this ledger entry?');">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
                                                            <input type="hidden" name="student_id" value="<?= (int) $studentInfo['id'] ?>">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                            <button type="submit" class="btn-suspend" style="padding: 4px 10px; font-size: 11px;">Delete</button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Add New Ledger Entry Form -->
                        <div style="margin-top: 24px;">
                            <h4 style="margin: 0 0 16px; font-size: 14px; font-weight: 700; color: var(--navy);">Add New Ledger Entry</h4>
                            <form method="post" action="/admin/process_payment.php" class="user-form" style="max-width: 600px;">
                                <input type="hidden" name="action" value="create">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="student_id" value="<?= (int) $studentInfo['id'] ?>">

                                <div class="form-grid">
                                    <div class="form-field">
                                        <label for="academic_year">Academic Year *</label>
                                        <input type="text" id="academic_year" name="academic_year" required value="<?= htmlspecialchars(get_academic_year($pdo)) ?>" style="height: 40px;">
                                    </div>
                                    <div class="form-field">
                                        <label for="description">Description *</label>
                                        <input type="text" id="description" name="description" required placeholder="e.g. Tuition Fee - 1st Semester" style="height: 40px;">
                                    </div>
                                    <div class="form-field">
                                        <label for="amount_due">Amount Due *</label>
                                        <input type="number" id="amount_due" name="amount_due" step="0.01" min="0" required value="0.00" style="height: 40px;">
                                    </div>
                                    <div class="form-field">
                                        <label for="amount_paid">Amount Paid</label>
                                        <input type="number" id="amount_paid" name="amount_paid" step="0.01" min="0" value="0.00" style="height: 40px;">
                                    </div>
                                    <div class="form-field">
                                        <label for="status">Status *</label>
                                        <select id="status" name="status" required style="height: 40px;">
                                            <option value="unpaid">Unpaid</option>
                                            <option value="partial">Partial</option>
                                            <option value="paid">Paid</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-actions">
                                    <button type="submit" class="btn-create">Add Entry</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </article>
        </section>
    </main>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>