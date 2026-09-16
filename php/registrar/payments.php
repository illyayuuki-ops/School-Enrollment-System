<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_role('registrar');
require_once dirname(__DIR__) . '/db_connect.php';
require_once dirname(__DIR__) . '/includes/settings.php';

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
$totalAssessed = 0.00;
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

    // Total outstanding across all students
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount_due - amount_paid), 0) FROM student_ledgers");
    $totalOutstanding = (float) $stmt->fetchColumn();

    // Total assessed across all students (sum of amount_due, not just the selected student)
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount_due), 0) FROM student_ledgers");
    $totalAssessed = (float) $stmt->fetchColumn();

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
            SELECT s.id, s.student_number, u.full_name, s.program, s.year_level, u.email
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
                ORDER BY created_at DESC
            ");
            $stmt->execute(['sid' => $studentInfo['id']]);
            $ledger = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        // Empty on error
    }
}

$totalPages = max(1, (int) ceil($totalStudents / $perPage));
if ($page > $totalPages) $page = $totalPages;

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

$pageTitle = 'Payments / Assessment';
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
        <section class="content-grid">
            <article class="panel panel-wide">
                <div class="panel-heading"><div><span class="eyebrow">Financial</span><h2>Payments / Assessment</h2></div></div>

                <!-- Summary Cards -->
                <div class="balance-cards">
                    <div class="balance-card">
                        <div class="card-label">Total Assessed</div>
                        <div class="card-value">₱<?= number_format($totalAssessed, 2) ?></div>
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
                        <input type="search" id="search" name="search" placeholder="Student number, name, or email..." value="<?= htmlspecialchars($search) ?>" style="height: 40px;">
                    </form>
                    <span style="color: var(--muted); font-size: 13px;"><?= number_format($totalStudents) ?> student<?= $totalStudents !== 1 ? 's' : '' ?> found</span>
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
                                                        <button type="button" class="btn-create" onclick="openRecordPaymentModal(<?= (int) $l['id'] ?>, '<?= htmlspecialchars($l['description'], ENT_QUOTES) ?>', '<?= (float)$l['amount_due'] - (float)$l['amount_paid'] ?>')" style="padding: 4px 10px; font-size: 11px;">Record Payment</button>
                                                        <form method="post" action="/registrar/process_payment.php" style="display: inline;" onsubmit="return confirm('Delete this ledger entry?');">
                                                            <input type="hidden" name="action" value="delete_ledger">
                                                            <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
                                                            <input type="hidden" name="student_id" value="<?= (int) $studentInfo['id'] ?>">
                                                            <input type="hidden" name="student_number" value="<?= htmlspecialchars($studentInfo['student_number']) ?>">
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
                            <form method="post" action="/registrar/process_payment.php" class="user-form" style="max-width: 600px;">
                                <input type="hidden" name="action" value="create_ledger">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="student_id" value="<?= (int) $studentInfo['id'] ?>">
                                <input type="hidden" name="student_number" value="<?= htmlspecialchars($studentInfo['student_number']) ?>">

                                <div class="form-grid">
                                    <div class="form-field">
                                        <label for="academic_year">Academic Year *</label>
                                        <input type="text" id="academic_year" name="academic_year" required value="2026-2027" style="height: 40px;">
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

                        <!-- Record Payment Modal -->
                        <div id="recordPaymentModal" class="modal-backdrop" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
                            <div class="recovery-modal" style="background: #fff; border-radius: var(--radius); padding: 24px; max-width: 450px; width: 90%;">
                                <button type="button" class="modal-close" onclick="closeRecordPaymentModal()" style="position: absolute; top: 12px; right: 12px; background: none; border: none; font-size: 24px; cursor: pointer; color: var(--muted);">&times;</button>
                                <h3 style="margin: 0 0 16px; font-size: 16px; font-weight: 700; color: var(--navy);">Record Payment</h3>
                                <form method="post" action="/registrar/process_payment.php" id="recordPaymentForm">
                                    <input type="hidden" name="action" value="record_payment">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="ledger_id" id="rp_ledger_id">
                                    <input type="hidden" name="student_id" id="rp_student_id">
                                    <input type="hidden" name="student_number" id="rp_student_number">
                                    <div class="form-grid" style="display: grid; grid-template-columns: 1fr; gap: 14px;">
                                        <div class="form-field">
                                            <label for="rp_description">Description</label>
                                            <input type="text" id="rp_description" name="description" readonly style="height: 40px; background: #f8faf9;">
                                        </div>
                                        <div class="form-field">
                                            <label for="rp_amount_due">Amount Due</label>
                                            <input type="text" id="rp_amount_due" name="amount_due" readonly style="height: 40px; background: #f8faf9;">
                                        </div>
                                        <div class="form-field">
                                            <label for="rp_balance">Current Balance</label>
                                            <input type="text" id="rp_balance" name="balance" readonly style="height: 40px; background: #f8faf9;">
                                        </div>
                                        <div class="form-field">
                                            <label for="rp_amount">Payment Amount *</label>
                                            <input type="number" id="rp_amount" name="amount" step="0.01" min="0.01" required placeholder="Enter payment amount" style="height: 40px;">
                                        </div>
                                    </div>
                                    <div class="form-actions" style="margin-top: 16px; display: flex; gap: 12px; justify-content: flex-end;">
                                        <button type="submit" class="btn-create">Record Payment</button>
                                        <button type="button" class="btn-cancel" onclick="closeRecordPaymentModal()">Cancel</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Edit Ledger Modal -->
                        <div id="editLedgerModal" class="modal-backdrop" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
                            <div class="recovery-modal" style="background: #fff; border-radius: var(--radius); padding: 24px; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto;">
                                <button type="button" class="modal-close" onclick="closeEditLedgerModal()" style="position: absolute; top: 12px; right: 12px; background: none; border: none; font-size: 24px; cursor: pointer; color: var(--muted);">&times;</button>
                                <h3 style="margin: 0 0 16px; font-size: 16px; font-weight: 700; color: var(--navy);">Edit Ledger Entry</h3>
                                <form method="post" action="/registrar/process_payment.php" id="editLedgerForm">
                                    <input type="hidden" name="action" value="edit_ledger">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="id" id="edit_ledger_id">
                                    <input type="hidden" name="student_id" id="edit_ledger_student_id">
                                    <input type="hidden" name="student_number" id="edit_ledger_student_number">
                                    <div class="form-grid" style="display: grid; grid-template-columns: 1fr; gap: 14px;">
                                        <div class="form-field">
                                            <label for="edit_academic_year">Academic Year *</label>
                                            <input type="text" id="edit_academic_year" name="academic_year" required style="height: 40px;">
                                        </div>
                                        <div class="form-field">
                                            <label for="edit_description">Description *</label>
                                            <input type="text" id="edit_description" name="description" required style="height: 40px;">
                                        </div>
                                        <div class="form-field">
                                            <label for="edit_amount_due">Amount Due *</label>
                                            <input type="number" id="edit_amount_due" name="amount_due" step="0.01" min="0" required style="height: 40px;">
                                        </div>
                                        <div class="form-field">
                                            <label for="edit_amount_paid">Amount Paid</label>
                                            <input type="number" id="edit_amount_paid" name="amount_paid" step="0.01" min="0" style="height: 40px;">
                                        </div>
                                        <div class="form-field">
                                            <label for="edit_status">Status *</label>
                                            <select id="edit_status" name="status" required style="height: 40px;">
                                                <option value="unpaid">Unpaid</option>
                                                <option value="partial">Partial</option>
                                                <option value="paid">Paid</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-actions" style="margin-top: 16px; display: flex; gap: 12px; justify-content: flex-end;">
                                        <button type="submit" class="btn-save">Save Changes</button>
                                        <button type="button" class="btn-cancel" onclick="closeEditLedgerModal()">Cancel</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <script>
                        function openRecordPaymentModal(ledgerId, description, balance) {
                            document.getElementById('rp_ledger_id').value = ledgerId;
                            document.getElementById('rp_description').value = description;
                            document.getElementById('rp_balance').value = '₱' + parseFloat(balance).toFixed(2);
                            // We need to get amount_due and amount_paid from the row
                            const row = document.querySelector('tr[data-ledger-id="' + ledgerId + '"]');
                            if (row) {
                                const dueCell = row.querySelector('td.amount-due');
                                const paidCell = row.querySelector('td.amount-paid');
                                if (dueCell) document.getElementById('rp_amount_due').value = dueCell.textContent.replace('₱', '').replace(/,/g, '');
                                // rp_amount_paid is not present in the Record Payment modal; guard against its absence.
                                const paidEl = document.getElementById('rp_amount_paid');
                                if (paidEl && paidCell) paidEl.value = paidCell.textContent.replace('₱', '').replace(/,/g, '');
                            }
                            document.getElementById('recordPaymentModal').style.display = 'flex';
                            document.getElementById('rp_amount').focus();
                        }

                        function closeRecordPaymentModal() {
                            document.getElementById('recordPaymentModal').style.display = 'none';
                        }

                        function openEditLedgerModal(id, academicYear, description, amountDue, amountPaid, status) {
                            document.getElementById('edit_ledger_id').value = id;
                            document.getElementById('edit_academic_year').value = academicYear;
                            document.getElementById('edit_description').value = description;
                            document.getElementById('edit_amount_due').value = amountDue;
                            document.getElementById('edit_amount_paid').value = amountPaid;
                            document.getElementById('edit_status').value = status;
                            document.getElementById('editLedgerModal').style.display = 'flex';
                        }

                        function closeEditLedgerModal() {
                            document.getElementById('editLedgerModal').style.display = 'none';
                        }

                        // Close modals on backdrop click
                        document.getElementById('recordPaymentModal')?.addEventListener('click', function(e) {
                            if (e.target === this) closeRecordPaymentModal();
                        });
                        document.getElementById('editLedgerModal')?.addEventListener('click', function(e) {
                            if (e.target === this) closeEditLedgerModal();
                        });

                        // Add data attributes to ledger rows for JS access
                        document.addEventListener('DOMContentLoaded', function() {
                            document.querySelectorAll('.ledger-row').forEach(row => {
                                const id = row.querySelector('.btn-edit')?.getAttribute('onclick')?.match(/openEditLedgerModal\((\d+)/)?.[1];
                                if (id) row.setAttribute('data-ledger-id', id);
                            });
                        });
                        </script>
                    </div>
                <?php endif; ?>
            </article>
        </section>
    </main>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>