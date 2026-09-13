<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_role('teacher');
require_once dirname(__DIR__) . '/db_connect.php';
require_once dirname(__DIR__) . '/includes/settings.php';

$pdo = db_connect();
$teacherId = (int) ($_SESSION['user']['id'] ?? 0);

$academicYear = get_academic_year($pdo);

$classes = [];
try {
    $stmt = $pdo->prepare('SELECT subject_code, subject_name, schedule_grid, room FROM classes_and_schedules WHERE teacher_id = :tid ORDER BY subject_code');
    $stmt->execute(['tid' => $teacherId]);
    $classes = $stmt->fetchAll();
} catch (PDOException $e) {
    // Empty on error
}

// Parse schedule_grid into day/time slots
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$daySlots = [];
foreach ($days as $day) {
    $daySlots[$day] = [];
}
foreach ($classes as $c) {
    $grid = $c['schedule_grid'] ?? '';
    $parts = preg_split('/\s*,\s*/', $grid);
    foreach ($parts as $part) {
        $part = trim($part);
        if (preg_match('/^([A-Za-z]{3})\s+(\d{1,2}:\d{2})-(\d{1,2}:\d{2})$/i', $part, $m)) {
            $dayAbbr = strtolower($m[1]);
            $dayMap = ['mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday'];
            $dayName = $dayMap[$dayAbbr] ?? null;
            if ($dayName) {
                $daySlots[$dayName][] = [
                    'subject' => $c['subject_code'] . ' - ' . $c['subject_name'],
                    'room' => $c['room'],
                    'start' => $m[2],
                    'end' => $m[3],
                ];
            }
        }
    }
}

// Generate time slots from 7:00 to 20:00 in 30-min increments
$timeSlots = [];
for ($h = 7; $h <= 19; $h++) {
    for ($m = 0; $m < 60; $m += 30) {
        $timeSlots[] = sprintf('%02d:%02d', $h, $m);
    }
}

$pageTitle = 'Schedule Matrix';
$extraStyles = ['/assets/teacher.css'];
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="app-frame">
    <?php require dirname(__DIR__) . '/includes/sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div><span class="eyebrow">Faculty workspace</span><h1>Welcome, <?= htmlspecialchars(explode(' ', $_SESSION['user']['full_name'])[0], ENT_QUOTES, 'UTF-8') ?>.</h1></div>
            <div class="topbar-actions"><span class="year-pill"><?= htmlspecialchars(get_academic_year($pdo)) ?></span><a class="avatar" href="#" onclick="return openLogoutModal()" title="Sign out"><?= strtoupper(substr((string) $_SESSION['user']['full_name'], 0, 1)) ?></a></div>
        </header>
        <section class="content-grid">
            <article class="panel panel-wide">
                <div class="panel-heading"><div><span class="eyebrow">Weekly Schedule</span><h2>Schedule Matrix</h2></div></div>
                <?php if (empty($classes)): ?>
                    <div class="empty-state">
                        <p>No classes assigned yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-wrapper" style="overflow-x: auto;">
                        <div class="schedule-grid">
                            <div class="header time-header">Time</div>
                            <?php foreach ($days as $day): ?>
                                <div class="header day-header"><?= $day ?></div>
                            <?php endforeach; ?>
                            <?php foreach ($timeSlots as $idx => $slot): ?>
                                <div class="time-slot"><?= $slot ?></div>
                                <?php foreach ($days as $day): ?>
                                    <div class="day-cell">
                                        <?php
                                        $found = false;
                                        foreach ($daySlots[$day] as $cls) {
                                            $start = $cls['start'] ?? '';
                                            $end = $cls['end'] ?? '';
                                            if ($start && $end && strtotime($slot) >= strtotime($start) && strtotime($slot) < strtotime($end)) {
                                                if (!$found) {
                                                    echo '<div class="class-block"><span class="subject">' . htmlspecialchars($cls['subject']) . '</span><span class="room">' . htmlspecialchars($cls['room']) . '</span></div>';
                                                    $found = true;
                                                }
                                            }
                                        }
                                        if (!$found) {
                                            echo '<span class="empty-slot">—</span>';
                                        }
                                        ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </article>
        </section>
    </main>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>