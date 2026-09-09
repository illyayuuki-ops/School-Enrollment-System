<?php
declare(strict_types=1);

$role = $_SESSION['user']['role'] ?? 'guest';
$navigation = [
    'admin' => [
        ['label' => 'Overview', 'href' => '/dashboard.php'],
        ['label' => 'System logs', 'href' => '/admin/logs.php'],
        ['label' => 'User accounts', 'href' => '/admin/users.php'],
        ['label' => 'Global settings', 'href' => '/admin/settings.php'],
    ],
    'admission' => [
        ['label' => 'Overview', 'href' => '/dashboard.php'],
        ['label' => 'Verification queue', 'href' => '/admission/queue.php'],
        ['label' => 'Document review', 'href' => '/admission/documents.php'],
    ],
    'registrar' => [
        ['label' => 'Overview', 'href' => '/dashboard.php'],
        ['label' => 'Student masterlist', 'href' => '/registrar/students.php'],
        ['label' => 'Sectioning', 'href' => '/registrar/sections.php'],
        ['label' => 'Subject allocation', 'href' => '/registrar/subjects.php'],
    ],
    'teacher' => [
        ['label' => 'Overview', 'href' => '/dashboard.php'],
        ['label' => 'Active classes', 'href' => '/teacher/classes.php'],
        ['label' => 'Grade encoding', 'href' => '/teacher/grades.php'],
        ['label' => 'Schedule matrix', 'href' => '/teacher/schedule.php'],
    ],
    'student' => [
        ['label' => 'Overview', 'href' => '/dashboard.php'],
        ['label' => 'Enrollment wizard', 'href' => '/student/enrollment.php'],
        ['label' => 'Study load', 'href' => '/student/load.php'],
        ['label' => 'Application status', 'href' => '/student/status.php'],
    ],
][$role] ?? [['label' => 'Sign in', 'href' => '/login.php']];
?>
<aside class="sidebar">
    <a class="brand" href="/dashboard.php" aria-label="Northstar Enrollment home">
        <span class="brand-mark">N</span>
        <span><strong>Northstar</strong><small>Enrollment OS</small></span>
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