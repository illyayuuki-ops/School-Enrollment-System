<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_role('student');
require_once dirname(__DIR__) . '/db_connect.php';
require_once dirname(__DIR__) . '/includes/settings.php';

$pdo = db_connect();
$studentId = (int) ($_SESSION['user']['id'] ?? 0);

$academicYear = get_academic_year($pdo);

// Get student record with personal info from pre_registrations
$student = null;
$application = null;
try {
    $stmt = $pdo->prepare("
        SELECT s.*, u.email, u.full_name
        FROM students s
        JOIN users u ON s.user_id = u.id
        WHERE s.user_id = :uid
    ");
    $stmt->execute(['uid' => $studentId]);
    $student = $stmt->fetch();

    if ($student) {
        // Get pre_registration data for additional info
        $stmt = $pdo->prepare("
            SELECT * FROM pre_registrations
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

$pageTitle = 'Profile';
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
                <div class="panel-heading"><div><span class="eyebrow">Profile</span><h2>Personal Information</h2></div></div>

                <?php if ($student): ?>
                    <div class="detail-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px; margin-bottom: 24px;">
                        <div class="detail-field">
                            <label>Student Number</label>
                            <div class="value"><?= htmlspecialchars($student['student_number'] ?? '—') ?></div>
                        </div>
                        <div class="detail-field">
                            <label>Full Name</label>
                            <div class="value"><?= htmlspecialchars($_SESSION['user']['full_name'] ?? '—') ?></div>
                        </div>
                        <div class="detail-field">
                            <label>Email</label>
                            <div class="value"><?= htmlspecialchars($_SESSION['user']['email'] ?? '—') ?></div>
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
                    <div class="detail-section" style="margin-top: 24px;">
                        <h4>Contact Information</h4>
                        <div class="detail-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                            <div class="detail-field">
                                <label>Contact Number</label>
                                <div class="value"><?= htmlspecialchars($application['contact_number'] ?? '—') ?></div>
                            </div>
                            <div class="detail-field">
                                <label>LRN</label>
                                <div class="value"><?= htmlspecialchars($application['lrn'] ?? '—') ?></div>
                            </div>
                            <div class="detail-field">
                                <label>Date of Birth</label>
                                <div class="value"><?= $application['date_of_birth'] ? date('F d, Y', strtotime($application['date_of_birth'])) : '—' ?></div>
                            </div>
                            <div class="detail-field">
                                <label>Place of Birth</label>
                                <div class="value"><?= htmlspecialchars($application['place_of_birth'] ?? '—') ?></div>
                            </div>
                            <div class="detail-field">
                                <label>Gender</label>
                                <div class="value"><?= htmlspecialchars($application['gender'] ?? '—') ?></div>
                            </div>
                            <div class="detail-field">
                                <label>Nationality</label>
                                <div class="value"><?= htmlspecialchars($application['nationality'] ?? '—') ?></div>
                            </div>
                            <div class="detail-field">
                                <label>Religion</label>
                                <div class="value"><?= htmlspecialchars($application['religion'] ?? '—') ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="detail-section" style="margin-top: 24px;">
                        <h4>Address</h4>
                        <div class="detail-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                            <div class="detail-field">
                                <label>Street No.</label>
                                <div class="value"><?= htmlspecialchars($application['street_no'] ?? '—') ?></div>
                            </div>
                            <div class="detail-field">
                                <label>Street Name</label>
                                <div class="value"><?= htmlspecialchars($application['street_name'] ?? '—') ?></div>
                            </div>
                            <div class="detail-field">
                                <label>Subdivision/Village</label>
                                <div class="value"><?= htmlspecialchars($application['subdivision'] ?? '—') ?></div>
                            </div>
                            <div class="detail-field">
                                <label>Province</label>
                                <div class="value"><?= htmlspecialchars($application['province'] ?? '—') ?></div>
                            </div>
                            <div class="detail-field">
                                <label>Town/City</label>
                                <div class="value"><?= htmlspecialchars($application['town_city'] ?? '—') ?></div>
                            </div>
                            <div class="detail-field">
                                <label>Barangay</label>
                                <div class="value"><?= htmlspecialchars($application['barangay'] ?? '—') ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="detail-section" style="margin-top: 24px;">
                        <h4>Parent / Guardian Information</h4>
                        <div class="detail-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                            <div class="detail-field">
                                <label>Father's Name</label>
                                <div class="value"><?= htmlspecialchars($application['father_name'] ?? '—') ?></div>
                            </div>
                            <div class="detail-field">
                                <label>Father's Occupation</label>
                                <div class="value"><?= htmlspecialchars($application['father_occupation'] ?? '—') ?></div>
                            </div>
                            <div class="detail-field">
                                <label>Father's Mobile</label>
                                <div class="value"><?= htmlspecialchars($application['father_mobile'] ?? '—') ?></div>
                            </div>
                            <div class="detail-field">
                                <label>Mother's Name</label>
                                <div class="value"><?= htmlspecialchars($application['mother_name'] ?? '—') ?></div>
                            </div>
                            <div class="detail-field">
                                <label>Mother's Occupation</label>
                                <div class="value"><?= htmlspecialchars($application['mother_occupation'] ?? '—') ?></div>
                            </div>
                            <div class="detail-field">
                                <label>Mother's Mobile</label>
                                <div class="value"><?= htmlspecialchars($application['mother_mobile'] ?? '—') ?></div>
                            </div>
                            <div class="detail-field">
                                <label>Guardian's Name</label>
                                <div class="value"><?= htmlspecialchars($application['guardian_name'] ?? '—') ?></div>
                            </div>
                            <div class="detail-field">
                                <label>Guardian's Occupation</label>
                                <div class="value"><?= htmlspecialchars($application['guardian_occupation'] ?? '—') ?></div>
                            </div>
                            <div class="detail-field">
                                <label>Guardian's Mobile</label>
                                <div class="value"><?= htmlspecialchars($application['guardian_mobile'] ?? '—') ?></div>
                            </div>
                            <div class="detail-field">
                                <label>Guardian's Email</label>
                                <div class="value"><?= htmlspecialchars($application['guardian_email'] ?? '—') ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="detail-section" style="margin-top: 24px;">
                        <h4>School Last Attended</h4>
                        <div class="detail-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                            <div class="detail-field">
                                <label>School Name</label>
                                <div class="value"><?= htmlspecialchars($application['school_last_attended'] ?? '—') ?></div>
                            </div>
                            <div class="detail-field">
                                <label>School Address</label>
                                <div class="value"><?= htmlspecialchars($application['school_address'] ?? '—') ?></div>
                            </div>
                            <div class="detail-field">
                                <label>School Year</label>
                                <div class="value"><?= htmlspecialchars($application['school_year'] ?? '—') ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="detail-section" style="margin-top: 24px;">
                        <h4>Additional Information</h4>
                        <div class="detail-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                            <div class="detail-field">
                                <label>Comorbidity</label>
                                <div class="value"><?= htmlspecialchars($application['comorbidity'] ?? '—') ?></div>
                            </div>
                            <div class="detail-field">
                                <label>Parental Consent</label>
                                <div class="value"><?= htmlspecialchars($application['parental_consent'] ?? '—') ?></div>
                            </div>
                            <div class="detail-field">
                                <label>Mode of Transportation</label>
                                <div class="value"><?= htmlspecialchars($application['mode_of_transportation'] ?? '—') ?></div>
                            </div>
                            <div class="detail-field">
                                <label>Pre-Admission Code</label>
                                <div class="value"><?= htmlspecialchars($application['pre_admission_code'] ?? '—') ?></div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <p>Student record not found.</p>
                    </div>
                <?php endif; ?>
                <?php endif; ?>
            </article>
        </section>
    </main>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>