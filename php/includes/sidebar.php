<?php
declare(strict_types=1);

$role = $_SESSION['user']['role'] ?? 'guest';
$roleHome = $role === 'guest'
    ? '/login/student.php'
    : (function_exists('home_for_role') ? home_for_role($role) : "/$role/home.php");
$navigation = [
    'admin' => [
        ['label' => 'Dashboard', 'href' => $roleHome],
        ['label' => 'System Logs', 'href' => '/admin/logs.php'],
        ['label' => 'User Accounts', 'href' => '/admin/users.php'],
        ['label' => 'Student Masterlist', 'href' => '/admin/students.php'],
        ['label' => 'Payments', 'href' => '/admin/payments.php'],
        ['label' => 'Pre-admission List', 'href' => '/admin/pre_admission.php'],
        ['label' => 'Teacher Attendance', 'href' => '/admin/teacher_attendance.php'],
        ['label' => 'Global Settings', 'href' => '/admin/settings.php'],
    ],
    'admission' => [
        ['label' => 'Dashboard', 'href' => $roleHome],
        ['label' => 'Pre-admission List', 'href' => '/admission/pre_admission.php'],
        ['label' => 'Verification Queue', 'href' => '/admission/queue.php'],
        ['label' => 'Document Review', 'href' => '/admission/documents.php'],
    ],
    'registrar' => [
        ['label' => 'Dashboard', 'href' => $roleHome],
        ['label' => 'Student Masterlist', 'href' => '/registrar/students.php'],
        ['label' => 'Sectioning', 'href' => '/registrar/sections.php'],
        ['label' => 'Subject Allocation', 'href' => '/registrar/subjects.php'],
        ['label' => 'Payments / Assessment', 'href' => '/registrar/payments.php'],
    ],
    'teacher' => [
        ['label' => 'Dashboard', 'href' => $roleHome],
        ['label' => 'Active Classes', 'href' => '/teacher/classes.php'],
        ['label' => 'Grade Encoding', 'href' => '/teacher/grades.php'],
        ['label' => 'Schedule Matrix', 'href' => '/teacher/schedule.php'],
    ],
    'student' => [
        ['label' => 'Dashboard', 'href' => $roleHome],
        ['label' => 'Enrollment', 'href' => '/student/enrollment.php'],
        ['label' => 'Study Load', 'href' => '/student/load.php'],
        ['label' => 'Application Status', 'href' => '/student/status.php'],
    ],
][$role] ?? [['label' => 'Sign In', 'href' => '/login/student.php']];
?>
<aside class="sidebar">
    <a class="brand" href="<?= htmlspecialchars($roleHome, ENT_QUOTES, 'UTF-8') ?>" aria-label="Steiner Maris Academy SMA Portal">
        <img src="/assets/logo.png" alt="Steiner Maris Academy" style="height:32px;width:auto">
        <span><strong>Steiner Maris Academy</strong><small>SMA Portal</small></span>
    </a>
    <div class="sidebar-context">
        <span class="eyebrow">Academic year</span>
        <strong>A.Y. 2026–2027</strong>
    </div>
    <nav aria-label="Main navigation">
        <?php foreach ($navigation as $item): ?>
            <a class="nav-link" href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>">
                <span class="nav-dot" aria-hidden="true"></span>
                <?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer">
        <span class="eyebrow">Need help?</span>
        <p>Contact the registrar's office for enrollment support.</p>
    </div>
</aside>