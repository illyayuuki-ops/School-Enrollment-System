<?php
declare(strict_types=1);

$role = $_SESSION['user']['role'] ?? 'guest';
$roleHome = $role === 'guest'
    ? '/login/student.php'
    : (function_exists('home_for_role') ? home_for_role($role) : "/$role/home.php");
require_once dirname(__DIR__) . '/includes/settings.php';

$academicYearLabel = academic_year_label($pdo);

// Determine current page for active navigation highlighting
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
$currentPath = rtrim($currentPath, '/');

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
        ['label' => 'My Attendance', 'href' => '/teacher/attendance.php'],
    ],
    'student' => [
        ['label' => 'Dashboard', 'href' => $roleHome],
        ['label' => 'Profile', 'href' => '/student/profile.php'],
        ['label' => 'Study Load', 'href' => '/student/load.php'],
        ['label' => 'Application Status', 'href' => '/student/status.php'],
        ['label' => 'Statement of Account', 'href' => '/student/statement.php'],
        ['label' => 'Enrollment', 'href' => '/student/enrollment.php'],
        ['label' => 'Grades', 'href' => '/student/grades.php'],
        ['label' => 'Subjects', 'href' => '/student/subjects.php'],
        ['label' => 'Academic Policies', 'href' => '/student/policies.php'],
        ['label' => 'FAQ', 'href' => '/student/faq.php'],
    ],
];
$navigation = $navigation[$role] ?? [['label' => 'Log in', 'href' => '/login/student.php']];

// Helper to determine if a nav item is active
function is_active_nav(string $href, string $currentPath, string $roleHome): bool {
    $hrefPath = parse_url($href, PHP_URL_PATH) ?? '';
    $hrefPath = rtrim($hrefPath, '/');
    // Dashboard ($roleHome) is only active on exact match
    if ($hrefPath === $roleHome) {
        return $hrefPath === $currentPath;
    }
    // Other items: match if current path starts with the item's path
    return str_starts_with($currentPath, $hrefPath);
}
?>
<aside class="sidebar">
    <a class="brand" href="<?= htmlspecialchars($roleHome, ENT_QUOTES, 'UTF-8') ?>" aria-label="Steiner Maris Academy SMA Portal">
        <img src="/assets/logo.png" alt="Steiner Maris Academy" style="height:32px;width:auto">
        <span><strong>Steiner Maris Academy</strong><small>SMA Portal</small></span>
    </a>
    <div class="sidebar-context">
        <span class="eyebrow">Academic year</span>
        <strong><?= htmlspecialchars(academic_year_label($pdo)) ?></strong>
    </div>
    <nav aria-label="Main navigation">
        <?php foreach ($navigation as $item): ?>
            <?php $active = is_active_nav($item['href'], $currentPath, $roleHome); ?>
            <a class="nav-link<?= $active ? ' active' : '' ?>" href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>">
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