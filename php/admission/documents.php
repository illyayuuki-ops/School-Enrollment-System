<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_role('admission');
require_once dirname(__DIR__) . '/db_connect.php';

$pdo = db_connect();

$appId = (int) ($_GET['id'] ?? 0);

$application = null;
if ($appId) {
    try {
        $stmt = $pdo->prepare("
            SELECT pr.*, u.full_name
            FROM pre_registrations pr
            LEFT JOIN users u ON pr.email = u.email
            WHERE pr.id = :id
        ");
        $stmt->execute(['id' => $appId]);
        $application = $stmt->fetch();
    } catch (PDOException $e) {
        // Empty on error
    }
}

$pageTitle = 'Document Review';
$extraStyles = ['/assets/admission.css'];
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="app-frame">
    <?php require dirname(__DIR__) . '/includes/sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div><span class="eyebrow">Admissions workspace</span><h1>Welcome, <?= htmlspecialchars(explode(' ', $_SESSION['user']['full_name'])[0], ENT_QUOTES, 'UTF-8') ?>.</h1></div>
            <div class="topbar-actions"><span class="year-pill">A.Y. 2026–2027</span><a class="avatar" href="#" onclick="return openLogoutModal()" title="Sign out"><?= strtoupper(substr((string) $_SESSION['user']['full_name'], 0, 1)) ?></a></div>
        </header>
        <section class="content-grid">
            <article class="panel panel-wide">
                <div class="panel-heading"><div><span class="eyebrow">Review</span><h2>Document Review</h2></div></div>

                <?php if (!$application): ?>
                    <div class="empty-state">
                        <p>Application not found.</p>
                        <p style="margin-top: 12px;"><a href="/admission/queue.php" class="button button-primary">← Back to Queue</a></p>
                    </div>
                <?php else: ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <div>
                            <strong>Application #:</strong> <?= htmlspecialchars($application['application_number'] ?? '—') ?>
                            <span style="margin-left: 16px;">
                                <span class="status-badge status-<?= htmlspecialchars($application['status'] ?? 'pending') ?>">
                                    <?= htmlspecialchars(ucfirst($application['status'] ?? 'pending')) ?>
                                </span>
                            </span>
                        </div>
                        <a href="/admission/queue.php" class="button button-secondary">← Back to Queue</a>
                    </div>

                    <div class="detail-section">
                        <h4>Applicant Information</h4>
                        <div class="detail-grid">
                            <div class="detail-field">
                                <label>Full Name</label>
                                <div class="value"><?= htmlspecialchars($application['full_name'] ?? $application['last_name'] . ' ' . $application['first_name'] . ' ' . ($application['middle_name'] ?? '')) ?></div>
                            </div>
                            <div class="detail-field">
                                <label>Email</label>
                                <div class="value"><?= htmlspecialchars($application['email'] ?? '—') ?></div>
                            </div>
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

                    <div class="detail-section">
                        <h4>Program Selection</h4>
                        <div class="detail-grid">
                            <div class="detail-field">
                                <label>Enrollee Type</label>
                                <div class="value"><?= htmlspecialchars(ucfirst($application['enrollee_type'] ?? 'new')) ?></div>
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
                                <label>Semester</label>
                                <div class="value"><?= htmlspecialchars($application['semester'] ?? '—') ?></div>
                            </div>
                            <div class="detail-field">
                                <label>Modality</label>
                                <div class="value"><?= htmlspecialchars($application['modality'] ?? '—') ?></div>
                            </div>
                            <div class="detail-field">
                                <label>First Choice Course</label>
                                <div class="value"><?= htmlspecialchars($application['first_choice_course'] ?? '—') ?></div>
                            </div>
                            <div class="detail-field">
                                <label>First Major/Track</label>
                                <div class="value"><?= htmlspecialchars($application['first_major_track'] ?? '—') ?></div>
                            </div>
                            <div class="detail-field">
                                <label>Second Choice Course</label>
                                <div class="value"><?= htmlspecialchars($application['second_choice_course'] ?? '—') ?></div>
                            </div>
                            <div class="detail-field">
                                <label>Second Major/Track</label>
                                <div class="value"><?= htmlspecialchars($application['second_major_track'] ?? '—') ?></div>
                            </div>
                            <div class="detail-field">
                                <label>NC Level</label>
                                <div class="value"><?= htmlspecialchars($application['nc_level'] ?? '—') ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="detail-section">
                        <h4>Address</h4>
                        <div class="detail-grid">
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

                    <div class="detail-section">
                        <h4>Parent / Guardian Information</h4>
                        <div class="detail-grid">
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

                    <div class="detail-section">
                        <h4>School Last Attended</h4>
                        <div class="detail-grid">
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

                    <div class="detail-section">
                        <h4>Additional Information</h4>
                        <div class="detail-grid">
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

                    <div style="margin-top: 20px; display: flex; gap: 12px; justify-content: flex-end;">
                        <a href="/admission/process_application.php?id=<?= urlencode($application['id']) ?>&action=approve" class="btn-approve" onclick="return confirm('Approve this application?');">Approve</a>
                        <a href="/admission/process_application.php?id=<?= urlencode($application['id']) ?>&action=reject" class="btn-reject" onclick="return confirm('Reject this application?');">Reject</a>
                    </div>
                <?php endif; ?>
            </article>
        </section>
    </main>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>