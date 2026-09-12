<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_role('student');
require_once dirname(__DIR__) . '/db_connect.php';

$pdo = db_connect();
$studentId = (int) ($_SESSION['user']['id'] ?? 0);

// Get student record
$student = null;
$application = null;
try {
    $stmt = $pdo->prepare("SELECT student_number, program, year_level, enrollment_status FROM students WHERE user_id = :uid");
    $stmt->execute(['uid' => $studentId]);
    $student = $stmt->fetch();

    if ($student) {
        // Get latest application
        $stmt = $pdo->prepare("
            SELECT application_number, level_type, level, first_choice_course, status, created_at
            FROM pre_registrations
            WHERE email = (SELECT email FROM users WHERE id = :uid)
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute(['uid' => $studentId]);
        $application = $stmt->fetch();
    }
} catch (PDOException $e) {
    // Keep defaults on error
}

$pageTitle = 'Application Status';
$extraStyles = ['/assets/student.css'];
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="app-frame">
    <?php require dirname(__DIR__) . '/includes/sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div><span class="eyebrow">Student workspace</span><h1>Welcome, <?= htmlspecialchars(explode(' ', $_SESSION['user']['full_name'])[0], ENT_QUOTES, 'UTF-8') ?>.</h1></div>
            <div class="topbar-actions"><span class="year-pill">A.Y. 2026–2027</span><a class="avatar" href="#" onclick="return openLogoutModal()" title="Sign out"><?= strtoupper(substr((string) $_SESSION['user']['full_name'], 0, 1)) ?></a></div>
        </header>
        <section class="content-grid">
            <article class="panel panel-wide">
                <div class="panel-heading"><div><span class="eyebrow">Application Status</span><h2>Enrollment Summary</h2></div></div>
                
                <div class="detail-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px; margin-bottom: 24px;">
                    <div class="detail-field">
                        <label>Student Number</label>
                        <div class="value"><?= htmlspecialchars($student['student_number'] ?? '—') ?></div>
                    </div>
                    <div class="detail-field">
                        <label>Program</label>
                        <div class="value"><?= htmlspecialchars($student['program'] ?? '—') ?></div>
                    </div>
                    <div class="detail-field">
                        <label>Year Level</label>
                        <div class="value"><?= htmlspecialchars($student['year_level'] ?? '—') ?></div>
                    </div>
                    <div class="detail-field">
                        <label>Enrollment Status</label>
                        <div class="value">
                            <span class="status-badge status-<?= $student['enrollment_status'] === 'enrolled' ? 'paid' : ($student['enrollment_status'] === 'pending' ? 'partial' : 'unpaid') ?>" style="font-size: 11px; text-transform: capitalize;">
                                <?= htmlspecialchars(ucfirst($student['enrollment_status'] ?? '—')) ?>
                            </span>
                        </div>
                    </div>
                </div>

                <?php if ($application): ?>
                    <div class="detail-section">
                        <h4>Latest Application</h4>
                        <div class="detail-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                            <div class="detail-field">
                                <label>Application Number</label>
                                <div class="value"><?= htmlspecialchars($application['application_number'] ?? '—') ?></div>
                            </div>
                            <div class="detail-field">
                                <label>Level Type</label>
                                <div class="value"><?= htmlspecialchars($application['level_type'] ?? '—') ?></div>
                            </div>
                            <div class="detail-field">
                                <label>Level</label>
                                <div class="value"><?= htmlspecialchars($application['level'] ?? '—') ?></div>
                            </div>
                            <div class="detail-field">
                                <label>First Choice Course</label>
                                <div class="value"><?= htmlspecialchars($application['first_choice_course'] ?? '—') ?></div>
                            </div>
                            <div class="detail-field">
                                <label>Status</label>
                                <div class="value">
                                    <span class="status-badge status-<?= htmlspecialchars($application['status'] ?? 'pending') ?>">
                                        <?= htmlspecialchars(ucfirst($application['status'] ?? 'pending')) ?>
                                    </span>
                                </div>
                            </div>
                            <div class="detail-field">
                                <label>Submitted</label>
                                <div class="value"><?= $application['created_at'] ? date('M d, Y H:i', strtotime($application['created_at'])) : '—' ?></div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <p>No application record found.</p>
                    </div>
                <?php endif; ?>
            </article>
        </section>
    </main>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>