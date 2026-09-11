<?php
declare(strict_types=1);

$role = $_SESSION['user']['role'] ?? 'guest';
$roleHome = $role === 'guest'
    ? '/login/student.php'
    : (function_exists('home_for_role') ? home_for_role($role) : "/$role/home.php");
$navigation = [
    'admin' => [
        ['label' => 'Overview', 'href' => $roleHome],
        ['label' => 'System logs', 'href' => '/admin/logs.php'],
        ['label' => 'User accounts', 'href' => '/admin/users.php'],
        ['label' => 'Global settings', 'href' => '/admin/settings.php'],
    ],
    'admission' => [
        ['label' => 'Overview', 'href' => $roleHome],
        ['label' => 'Verification queue', 'href' => '/admission/queue.php'],
        ['label' => 'Document review', 'href' => '/admission/documents.php'],
    ],
    'registrar' => [
        ['label' => 'Overview', 'href' => $roleHome],
        ['label' => 'Student masterlist', 'href' => '/registrar/students.php'],
        ['label' => 'Sectioning', 'href' => '/registrar/sections.php'],
        ['label' => 'Subject allocation', 'href' => '/registrar/subjects.php'],
        ['label' => 'Payments / Assessment', 'href' => '/registrar/payments.php'],
    ],
    'teacher' => [
        ['label' => 'Overview', 'href' => $roleHome],
        ['label' => 'Active classes', 'href' => '/teacher/classes.php'],
        ['label' => 'Grade encoding', 'href' => '/teacher/grades.php'],
        ['label' => 'Schedule matrix', 'href' => '/teacher/schedule.php'],
    ],
    'student' => [
        ['label' => 'Overview', 'href' => $roleHome],
        ['label' => 'Enrollment wizard', 'href' => '/student/enrollment.php'],
        ['label' => 'Study load', 'href' => '/student/load.php'],
        ['label' => 'Application status', 'href' => '/student/status.php'],
    ],
][$role] ?? [['label' => 'Sign in', 'href' => '/login/student.php']];
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