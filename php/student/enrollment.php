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
try {
    $stmt = $pdo->prepare("SELECT student_number, program, year_level FROM students WHERE user_id = :uid");
    $stmt->execute(['uid' => $studentId]);
    $student = $stmt->fetch();
} catch (PDOException $e) {
    // Keep defaults on error
}

// Get enrollment steps from student_enrollment_steps
$enrollmentSteps = [];
try {
    if ($student) {
        $stmt = $pdo->prepare("
            SELECT step_key, label, status, display_order
            FROM student_enrollment_steps
            WHERE student_id = :sid
            ORDER BY display_order
        ");
        $stmt->execute(['sid' => $student['student_number']]);
        $enrollmentSteps = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    // Fallback to static steps
    $enrollmentSteps = [];
}

$pageTitle = 'Enrollment';
$extraStyles = ['/assets/student.css'];
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="app-frame">
    <?php require dirname(__DIR__) . '/includes/sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div><span class="eyebrow">Student workspace</span><h1>Welcome, <?= htmlspecialchars(explode(' ', $_SESSION['user']['full_name'])[0], ENT_QUOTES, 'UTF-8') ?>.</h1></div>
            <div class="topbar-actions"><span class="year-pill"><?= htmlspecialchars($academicYear) ?></span><a class="avatar" href="#" onclick="return openLogoutModal()" title="Sign out"><?= strtoupper(substr((string) $_SESSION['user']['full_name'], 0, 1)) ?></a></div>
        </header>
        <section class="content-grid">
            <article class="panel panel-wide">
                <div class="panel-heading"><div><span class="eyebrow">Enrollment</span><h2>Enrollment Process</h2></div></div>
                
                <!-- Static enrollment process steps - always show 1-6 -->
                <ol class="step-list">
                    <li class="step-item">
                        <span class="step-number">1</span>
                        <div class="step-content">
                            <strong>Application</strong>
                            <span>Submit pre-registration application with required documents</span>
                        </div>
                    </li>
                    <li class="step-item">
                        <span class="step-number">2</span>
                        <div class="step-content">
                            <strong>Assessment</strong>
                            <span>Admissions office reviews application and verifies documents</span>
                        </div>
                    </li>
                    <li class="step-item">
                        <span class="step-number">3</span>
                        <div class="step-content">
                            <strong>Payment</strong>
                            <span>Pay assessed fees including application fee and tuition</span>
                        </div>
                    </li>
                    <li class="step-item">
                        <span class="step-number">4</span>
                        <div class="step-content">
                            <strong>Advising</strong>
                            <span>Meet with academic advisor to select courses and plan schedule</span>
                        </div>
                    </li>
                    <li class="step-item">
                        <span class="step-number">5</span>
                        <div class="step-content">
                            <strong>Enlistment</strong>
                            <span>Enroll in approved courses for the semester</span>
                        </div>
                    </li>
                    <li class="step-item">
                        <span class="step-number">6</span>
                        <div class="step-content">
                            <strong>Confirmation</strong>
                            <span>Receive official enrollment confirmation and student ID</span>
                        </div>
                    </li>
                </ol>
            </article>
        </section>
    </main>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>