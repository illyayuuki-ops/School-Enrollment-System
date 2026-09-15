<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_role('registrar');
require_once dirname(__DIR__) . '/db_connect.php';

$pdo = db_connect();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /registrar/payments.php');
    exit;
}

$csrfToken = (string) ($_POST['csrf_token'] ?? '');
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    header('Location: /registrar/payments.php?error=csrf');
    exit;
}

$action = $_POST['action'] ?? '';

// Resolve the student_number from student_id server-side so the redirect reliably
// returns to the same student's ledger view regardless of what the form posted.
function resolve_student_number($pdo, $studentId) {
    if (!$studentId) { return ''; }
    try {
        $stmt = $pdo->prepare("SELECT student_number FROM students WHERE id = :id");
        $stmt->execute(['id' => $studentId]);
        $row = $stmt->fetch();
        return $row ? (string) $row['student_number'] : '';
    } catch (PDOException $e) {
        return '';
    }
}

try {
    if ($action === 'record_payment') {
        $ledgerId = (int) ($_POST['ledger_id'] ?? 0);
        $amount = (float) ($_POST['amount'] ?? 0);
        $studentId = (int) ($_POST['student_id'] ?? 0);

        if (!$ledgerId || $amount <= 0 || !$studentId) {
            header('Location: /registrar/payments.php?error=validation');
            exit;
        }

        $pdo->beginTransaction();
        try {
            // Get current ledger entry
            $stmt = $pdo->prepare("SELECT amount_due, amount_paid FROM student_ledgers WHERE id = :id");
            $stmt->execute(['id' => $ledgerId]);
            $ledger = $stmt->fetch();

            if (!$ledger) {
                $pdo->rollBack();
                header('Location: /registrar/payments.php?error=not_found');
                exit;
            }

            $newAmountPaid = min($ledger['amount_paid'] + $amount, $ledger['amount_due']);
            $newStatus = $newAmountPaid >= $ledger['amount_due'] ? 'paid' : ($newAmountPaid > 0 ? 'partial' : 'unpaid');

            $stmt = $pdo->prepare("UPDATE student_ledgers SET amount_paid = :paid, status = :status WHERE id = :id");
            $stmt->execute(['paid' => $newAmountPaid, 'status' => $newStatus, 'id' => $ledgerId]);

            // Log the payment
            $stmt = $pdo->prepare("INSERT INTO system_logs (actor_user_id, action, detail, created_at) VALUES (:actor, 'payment_recorded', :detail, NOW())");
            $detail = "Recorded payment of ₱" . number_format($amount, 2) . " on ledger ID {$ledgerId}";
            $stmt->execute(['actor' => $_SESSION['user']['id'], 'detail' => $detail]);

            $pdo->commit();
            $studentNumber = resolve_student_number($pdo, $studentId);
            header('Location: /registrar/payments.php?student_id=' . urlencode($studentNumber) . '&success=Payment+recorded+successfully');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            header('Location: /registrar/payments.php?error=Database+error');
            exit;
        }
    } elseif ($action === 'create_ledger') {
        $studentId = (int) ($_POST['student_id'] ?? 0);
        $academicYear = trim((string) ($_POST['academic_year'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $amountDue = (float) ($_POST['amount_due'] ?? 0);
        $amountPaid = (float) ($_POST['amount_paid'] ?? 0);
        $status = (string) ($_POST['status'] ?? 'unpaid');

        $allowedStatuses = ['unpaid', 'partial', 'paid'];
        if (!$studentId || $academicYear === '' || $description === '' || $amountDue < 0 || $amountPaid < 0 || !in_array($status, $allowedStatuses, true)) {
            header('Location: /registrar/payments.php?error=validation');
            exit;
        }

        // Auto-compute status if not explicitly set to match amounts
        if ($amountPaid >= $amountDue && $amountDue > 0) {
            $status = 'paid';
        } elseif ($amountPaid > 0 && $amountPaid < $amountDue) {
            $status = 'partial';
        } else {
            $status = 'unpaid';
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                INSERT INTO student_ledgers (student_id, academic_year, description, amount_due, amount_paid, status, created_at)
                VALUES (:sid, :ay, :desc, :due, :paid, :status, NOW())
            ");
            $stmt->execute(['sid' => $studentId, 'ay' => $academicYear, 'desc' => $description, 'due' => $amountDue, 'paid' => $amountPaid, 'status' => $status]);

            // Log the creation
            $stmt = $pdo->prepare("INSERT INTO system_logs (actor_user_id, action, detail, created_at) VALUES (:actor, 'ledger_created', :detail, NOW())");
            $detail = "Created ledger entry for student ID {$studentId}: {$description} (Due: {$amountDue}, Paid: {$amountPaid})";
            $stmt->execute(['actor' => $_SESSION['user']['id'], 'detail' => $detail]);

            $pdo->commit();
            $studentNumber = resolve_student_number($pdo, $studentId);
            header('Location: /registrar/payments.php?student_id=' . urlencode($studentNumber) . '&success=Ledger+entry+created+successfully');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() === '23505') {
                header('Location: /registrar/payments.php?error=Duplicate+ledger+entry');
            } else {
                header('Location: /registrar/payments.php?error=Database+error');
            }
            exit;
        }
    } elseif ($action === 'edit_ledger') {
        $id = (int) ($_POST['id'] ?? 0);
        $academicYear = trim((string) ($_POST['academic_year'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $amountDue = (float) ($_POST['amount_due'] ?? 0);
        $amountPaid = (float) ($_POST['amount_paid'] ?? 0);
        $status = (string) ($_POST['status'] ?? 'unpaid');

        $allowedStatuses = ['unpaid', 'partial', 'paid'];
        if (!$id || $academicYear === '' || $description === '' || $amountDue < 0 || $amountPaid < 0 || !in_array($status, $allowedStatuses, true)) {
            header('Location: /registrar/payments.php?error=validation');
            exit;
        }

        // Auto-compute status
        if ($amountPaid >= $amountDue && $amountDue > 0) {
            $status = 'paid';
        } elseif ($amountPaid > 0 && $amountPaid < $amountDue) {
            $status = 'partial';
        } else {
            $status = 'unpaid';
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE student_ledgers SET academic_year = :ay, description = :desc, amount_due = :due, amount_paid = :paid, status = :status WHERE id = :id");
            $stmt->execute(['ay' => $academicYear, 'desc' => $description, 'due' => $amountDue, 'paid' => $amountPaid, 'status' => $status, 'id' => $id]);

            if ($stmt->rowCount() > 0) {
                $stmt = $pdo->prepare("INSERT INTO system_logs (actor_user_id, action, detail, created_at) VALUES (:actor, 'ledger_updated', :detail, NOW())");
                $detail = "Updated ledger entry ID {$id}";
                $stmt->execute(['actor' => $_SESSION['user']['id'], 'detail' => $detail]);
            }

            $pdo->commit();
            $stmt = $pdo->prepare("SELECT student_id FROM student_ledgers WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $ledgerOwner = $stmt->fetch();
            $studentNumber = $ledgerOwner ? resolve_student_number($pdo, (int) $ledgerOwner['student_id']) : '';
            header('Location: /registrar/payments.php?student_id=' . urlencode($studentNumber) . '&success=Ledger+updated+successfully');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            header('Location: /registrar/payments.php?error=Database+error');
            exit;
        }
    } elseif ($action === 'delete_ledger') {
        $id = (int) ($_POST['id'] ?? 0);
        $studentNumber = (string) ($_POST['student_number'] ?? '');

        if (!$id) {
            header('Location: /registrar/payments.php?error=invalid_id');
            exit;
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT student_id, description, amount_due, amount_paid FROM student_ledgers WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $ledger = $stmt->fetch();

            if (!$ledger) {
                $pdo->rollBack();
                header('Location: /registrar/payments.php?error=not_found');
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM student_ledgers WHERE id = :id");
            $stmt->execute(['id' => $id]);

            $stmt = $pdo->prepare("INSERT INTO system_logs (actor_user_id, action, detail, created_at) VALUES (:actor, 'ledger_deleted', :detail, NOW())");
            $detail = "Deleted ledger entry: {$ledger['description']} (Due: {$ledger['amount_due']}, Paid: {$ledger['amount_paid']})";
            $stmt->execute(['actor' => $_SESSION['user']['id'], 'detail' => $detail]);

            $pdo->commit();
            header('Location: /registrar/payments.php?student_id=' . urlencode($studentNumber) . '&success=Ledger+entry+deleted+successfully');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            header('Location: /registrar/payments.php?error=Database+error');
            exit;
        }
    }

    header('Location: /registrar/payments.php?error=unknown_action');
    exit;
} catch (Exception $e) {
    header('Location: /registrar/payments.php?error=unexpected');
    exit;
}