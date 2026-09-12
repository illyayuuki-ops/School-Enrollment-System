<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_role('admission');
require_once dirname(__DIR__) . '/db_connect.php';
require_once dirname(__DIR__) . '/includes/mailer.php';
require_once dirname(__DIR__) . '/includes/otp.php';

$pdo = db_connect();

$appId = (int) ($_GET['id'] ?? ($_POST['id'] ?? 0));
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if (!$appId || !in_array($action, ['approve', 'reject'], true)) {
    header('Location: /admission/queue.php');
    exit;
}

// Verify CSRF for all state-changing actions (both approve and reject)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admission/queue.php');
    exit;
}

$csrfToken = (string) ($_POST['csrf_token'] ?? '');
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    header('Location: /admission/queue.php?error=csrf');
    exit;
}

// Consolidate application fee reading
$feeAmount = max(0, (float) ($_POST['application_fee'] ?? 500.00));

// Fetch application
$application = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM pre_registrations WHERE id = :id AND status = 'pending'");
    $stmt->execute(['id' => $appId]);
    $application = $stmt->fetch();
} catch (PDOException $e) {
    // Empty on error
}

if (!$application) {
    header('Location: /admission/queue.php?error=not_found');
    exit;
}

if ($action === 'approve') {
    // Process approval with application fee
    $tempPassword = bin2hex(random_bytes(8));
    $passwordHash = password_hash($tempPassword, PASSWORD_DEFAULT);
    $studentNumber = 'STU-' . date('Y') . '-' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
    $email = $application['email'] ?? '';
    $fullName = trim(($application['first_name'] ?? '') . ' ' . ($application['last_name'] ?? ''));

    $pdo->beginTransaction();
    try {
        // Create user account (inactive until email activation)
        $stmt = $pdo->prepare("
            INSERT INTO users (email, password_hash, full_name, role, status, activated, created_at)
            VALUES (:email, :phash, :fname, 'student', 'active', FALSE, NOW())
            RETURNING id
        ");
        $stmt->execute(['email' => $email, 'phash' => $passwordHash, 'fname' => $fullName]);
        $userId = $stmt->fetchColumn();

        // Create student record
        $stmt = $pdo->prepare("
            INSERT INTO students (user_id, student_number, enrollment_status, academic_year, program, year_level)
            VALUES (:uid, :sn, 'enrolled', '2026-2027', :prog, :yr)
            RETURNING id
        ");
        $prog = $application['first_choice_course'] ?? 'General';
        $yr = $application['level'] ?? '1st Year';
        $stmt->execute(['uid' => $userId, 'sn' => $studentNumber, 'prog' => $prog, 'yr' => $yr]);
        $studentId = $stmt->fetchColumn();

        // Update application status
        $stmt = $pdo->prepare("UPDATE pre_registrations SET status = 'approved' WHERE id = :id");
        $stmt->execute(['id' => $appId]);

        // Create application fee ledger entry
        $stmt = $pdo->prepare("
            INSERT INTO student_ledgers (student_id, academic_year, description, amount_due, amount_paid, status, created_at)
            VALUES (:sid, '2026-2027', 'Application Fee', :due, 0, 'unpaid', NOW())
        ");
        $stmt->execute(['sid' => $studentId, 'due' => $feeAmount]);

        $pdo->commit();

        // Generate and send activation OTP
        $activationSent = false;
        $activationError = '';
        try {
            $activationCode = generate_and_store_otp($pdo, $userId, $email, 'activation');
            $activationSent = send_otp_email($email, $fullName, $activationCode, 'activation');
            if (!$activationSent) {
                $activationError = ' (activation email could not be sent)';
            }
        } catch (Exception $e) {
            error_log('Activation OTP error: ' . $e->getMessage());
            $activationError = ' (activation email failed)';
        }

        // Log the approval
        try {
            $stmt = $pdo->prepare("INSERT INTO system_logs (actor_user_id, action, detail, created_at) VALUES (:actor, 'application_approved', :detail, NOW())");
            $detail = "Approved application {$application['application_number']} ({$application['email']}) — student {$studentNumber} created with fee ₱" . number_format($feeAmount, 2);
            $stmt->execute(['actor' => $_SESSION['user']['id'], 'detail' => $detail]);
        } catch (PDOException $e) {
            // Ignore logging errors
        }

        // Success page with credentials
        $pageTitle = 'Application Approved';
        $extraStyles = ['/assets/admission.css'];
        require dirname(__DIR__) . '/includes/header.php';
        ?>
        <div class="app-frame">
            <?php require dirname(__DIR__) . '/includes/sidebar.php'; ?>
            <main class="main-content">
                <header class="topbar">
                    <div><span class="eyebrow">Admissions workspace</span><h1>Good morning, <?= htmlspecialchars(explode(' ', $_SESSION['user']['full_name'])[0], ENT_QUOTES, 'UTF-8') ?>.</h1></div>
                    <div class="topbar-actions"><span class="year-pill">A.Y. 2026–2027</span><a class="avatar" href="/logout.php" title="Sign out"><?= strtoupper(substr((string) $_SESSION['user']['full_name'], 0, 1)) ?></a></div>
                </header>
                <section class="content-grid">
                    <article class="panel panel-wide">
                        <div class="success-message">
                            <h3>Application Approved Successfully</h3>
                            <p>The application has been approved and a student account has been created.</p>
                            <div class="credential">
                                <div class="credential-label">Student Number</div>
                                <div class="credential-value"><?= htmlspecialchars($studentNumber) ?></div>
                            </div>
                            <div class="credential">
                                <div class="credential-label">Temporary Password</div>
                                <div class="credential-value"><?= htmlspecialchars($tempPassword) ?></div>
                            </div>
                            <div class="credential">
                                <div class="credential-label">Application Fee</div>
                                <div class="credential-value">₱<?= number_format($feeAmount, 2) ?></div>
                            </div>
                            <p style="margin-top: 16px; color: var(--muted); font-size: 13px;">
                                The applicant has been sent an <strong>activation code by email</strong> to activate their account. 
                                They must enter the activation code at <a href="/activate.php" style="color: var(--green);">/activate.php</a> 
                                and set their password before they can log in.
                                <?= $activationError ? '<br><span style="color: #dc2626;">' . htmlspecialchars($activationError) . '</span>' : '' ?>
                            </p>
                            <p style="margin-top: 8px;"><a href="/admission/queue.php" class="button button-primary">← Back to Queue</a></p>
                        </div>
                    </article>
                </section>
            </main>
        </div>
        <?php require dirname(__DIR__) . '/includes/footer.php'; ?>
        <?php
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        // Check for duplicate email error
        if ($e->getCode() === '23505') {
            $_SESSION['approve_error'] = 'Cannot approve: a user with this email already exists.';
        } else {
            error_log('Approval error: ' . $e->getMessage());
            $_SESSION['approve_error'] = 'A database error occurred during approval.';
        }
        header('Location: /admission/queue.php');
        exit;
    }
} elseif ($action === 'reject') {
    // Process rejection (POST only with CSRF)
    try {
        $stmt = $pdo->prepare("UPDATE pre_registrations SET status = 'rejected' WHERE id = :id AND status = 'pending'");
        $stmt->execute(['id' => $appId]);

        // Log the rejection
        try {
            $stmt = $pdo->prepare("INSERT INTO system_logs (actor_user_id, action, detail, created_at) VALUES (:actor, 'application_rejected', :detail, NOW())");
            $detail = "Rejected application {$application['application_number']} ({$application['email']})";
            $stmt->execute(['actor' => $_SESSION['user']['id'], 'detail' => $detail]);
        } catch (PDOException $e) {
            // Ignore logging errors
        }

        header('Location: /admission/queue.php?rejected=1');
        exit;
    } catch (PDOException $e) {
        // Fall through
    }
}

// Show approval form (GET for approve)
$feeAmount = 500.00; // Default application fee
$pageTitle = 'Approve Application';
$extraStyles = ['/assets/admission.css'];
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="app-frame">
    <?php require dirname(__DIR__) . '/includes/sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div><span class="eyebrow">Admissions workspace</span><h1>Good morning, <?= htmlspecialchars(explode(' ', $_SESSION['user']['full_name'])[0], ENT_QUOTES, 'UTF-8') ?>.</h1></div>
            <div class="topbar-actions"><span class="year-pill">A.Y. 2026–2027</span><a class="avatar" href="/logout.php" title="Sign out"><?= strtoupper(substr((string) $_SESSION['user']['full_name'], 0, 1)) ?></a></div>
        </header>
        <section class="content-grid">
            <article class="panel panel-wide">
                <div class="panel-heading"><div><span class="eyebrow">Approve</span><h2>Approve Application</h2></div></div>

                <div class="detail-section">
                    <h4>Applicant Information</h4>
                    <div class="detail-grid">
                        <div class="detail-field"><label>Name</label><div class="value"><?= htmlspecialchars(($application['first_name'] ?? '') . ' ' . ($application['last_name'] ?? '')) ?></div></div>
                        <div class="detail-field"><label>Email</label><div class="value"><?= htmlspecialchars($application['email'] ?? '—') ?></div></div>
                        <div class="detail-field"><label>Program</label><div class="value"><?= htmlspecialchars($application['first_choice_course'] ?? '—') ?></div></div>
                        <div class="detail-field"><label>Level</label><div class="value"><?= htmlspecialchars($application['level'] ?? '—') ?></div></div>
                    </div>
                </div>

                <?php if (isset($_SESSION['approve_error'])): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($_SESSION['approve_error']) ?></div>
                    <?php unset($_SESSION['approve_error']); ?>
                <?php endif; ?>

                <form method="post" action="/admission/process_application.php" class="approval-form">
                    <input type="hidden" name="id" value="<?= (int) $application['id'] ?>">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                    <div class="form-grid">
                        <div class="form-field">
                            <label for="application_fee">Application Fee (₱)</label>
                            <input type="number" id="application_fee" name="application_fee" step="0.01" min="0" value="500.00" required style="height: 40px;">
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-approve">Approve & Create Account</button>
                        <a href="/admission/queue.php" class="button button-secondary">Cancel</a>
                    </div>
                </form>

                <p style="margin-top: 16px; color: var(--muted); font-size: 13px;">
                    Approving will create a student account, generate a student number, and add the application fee to the student's ledger. The account will be inactive until the applicant activates it via the email code.
                </p>
            </article>
        </section>
    </main>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>