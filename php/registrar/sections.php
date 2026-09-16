<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_role('registrar');
require_once dirname(__DIR__) . '/db_connect.php';
require_once dirname(__DIR__) . '/includes/settings.php';

$pdo = db_connect();

$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Get all teachers for adviser dropdown
$teachers = [];
try {
    $stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE role = 'teacher' ORDER BY full_name");
    $stmt->execute();
    $teachers = $stmt->fetchAll();
} catch (PDOException $e) {
    // Empty on error
}

$where = '';
$params = [];

if ($search !== '') {
    $where = 'WHERE s.name ILIKE :search OR s.program ILIKE :search OR s.year_level ILIKE :search';
    $params['search'] = "%{$search}%";
}

// Total count
$total = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sections s {$where}");
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();
} catch (PDOException $e) {
    $total = 0;
}
$totalPages = max(1, (int) ceil($total / $perPage));
if ($page > $totalPages) $page = $totalPages;

// Fetch sections
$sections = [];
try {
    $stmt = $pdo->prepare("
        SELECT s.*, u.full_name as adviser_name,
               (SELECT COUNT(*) FROM students WHERE section_id = s.id) as student_count
        FROM sections s
        LEFT JOIN users u ON s.adviser_id = u.id
        {$where}
        ORDER BY s.level_type, s.program, s.year_level, s.name
        LIMIT :limit OFFSET :offset
    ");
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $sections = $stmt->fetchAll();
} catch (PDOException $e) {
    // Empty on error
}

// Get all students for assignment dropdown
$students = [];
try {
    $stmt = $pdo->prepare("
        SELECT s.id, s.student_number, u.full_name, s.program, s.year_level
        FROM students s
        JOIN users u ON s.user_id = u.id
        WHERE s.section_id IS NULL
        ORDER BY u.full_name
    ");
    $stmt->execute();
    $students = $stmt->fetchAll();
} catch (PDOException $e) {
    // Empty on error
}

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

$pageTitle = 'Sectioning';
$extraStyles = ['/assets/registrar.css'];
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="app-frame">
    <?php require dirname(__DIR__) . '/includes/sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div><span class="eyebrow">Registrar workspace</span><h1>Welcome, <?= htmlspecialchars(explode(' ', $_SESSION['user']['full_name'])[0], ENT_QUOTES, 'UTF-8') ?>.</h1></div>
            <div class="topbar-actions"><span class="year-pill"><?= htmlspecialchars(get_academic_year($pdo)) ?></span><a class="avatar" href="#" onclick="return openLogoutModal()" title="Sign out"><?= strtoupper(substr((string) $_SESSION['user']['full_name'], 0, 1)) ?></a></div>
        </header>
        <section class="content-grid">
            <article class="panel panel-wide">
                <div class="panel-heading"><div><span class="eyebrow">Records</span><h2>Sectioning</h2></div></div>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <!-- Create Section Form -->
                <div class="user-form" style="margin-bottom: 24px;">
                    <h3 style="margin: 0 0 16px; font-size: 14px; font-weight: 700; color: var(--navy);">Create Section</h3>
                    <form method="post" action="/registrar/process_section.php" class="user-form-inner">
                        <input type="hidden" name="action" value="create">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

                        <div class="form-grid">
                            <div class="form-field">
                                <label for="name">Section Name *</label>
                                <input type="text" id="name" name="name" required placeholder="e.g. BSCS-2A" style="height: 40px;">
                            </div>
                            <div class="form-field">
                                <label for="level_type">Level Type *</label>
                                <select id="level_type" name="level_type" required style="height: 40px;">
                                    <option value="">-- Select Level Type --</option>
                                    <option value="BED">BED</option>
                                    <option value="College">College</option>
                                    <option value="TESDA">TESDA</option>
                                </select>
                            </div>
                            <div class="form-field">
                                <label for="program">Program *</label>
                                <input type="text" id="program" name="program" required placeholder="e.g. BSCS" style="height: 40px;">
                            </div>
                            <div class="form-field">
                                <label for="year_level">Year Level *</label>
                                <select id="year_level" name="year_level" required style="height: 40px;">
                                    <option value="">-- Select Year Level --</option>
                                    <option value="Grade 7">Grade 7</option>
                                    <option value="Grade 8">Grade 8</option>
                                    <option value="Grade 9">Grade 9</option>
                                    <option value="Grade 10">Grade 10</option>
                                    <option value="Grade 11">Grade 11</option>
                                    <option value="Grade 12">Grade 12</option>
                                    <option value="1st Year">1st Year</option>
                                    <option value="2nd Year">2nd Year</option>
                                    <option value="3rd Year">3rd Year</option>
                                    <option value="4th Year">4th Year</option>
                                    <option value="NC I">NC I</option>
                                    <option value="NC II">NC II</option>
                                    <option value="NC III">NC III</option>
                                </select>
                            </div>
                            <div class="form-field">
                                <label for="adviser_id">Adviser</label>
                                <select id="adviser_id" name="adviser_id" style="height: 40px;">
                                    <option value="">-- No Adviser --</option>
                                    <?php foreach ($teachers as $t): ?>
                                        <option value="<?= (int) $t['id'] ?>"><?= htmlspecialchars($t['full_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-field">
                                <label for="capacity">Capacity *</label>
                                <input type="number" id="capacity" name="capacity" min="1" max="100" value="40" required style="height: 40px;">
                            </div>
                            <div class="form-field">
                                <label for="academic_year">Academic Year *</label>
                                <input type="text" id="academic_year" name="academic_year" required value="2026-2027" style="height: 40px;">
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn-create">Create Section</button>
                        </div>
                    </form>
                </div>

                <!-- Sections List -->
                <div class="search-bar" style="margin-top: 24px;">
                    <form method="get" action="/registrar/sections.php" class="form-field" style="flex: 1; min-width: 250px;">
                        <label for="search" class="eyebrow">Search</label>
                        <input type="search" id="search" name="search" placeholder="Section name, program, or year level..." value="<?= htmlspecialchars($search) ?>" style="height: 40px;">
                    </form>
                    <span style="color: var(--muted); font-size: 13px;"><?= number_format($total) ?> section<?= $total !== 1 ? 's' : '' ?></span>
                </div>

                <?php if (empty($sections)): ?>
                    <div class="empty-state">
                        <p>No sections found<?= $search ? ' matching "' . htmlspecialchars($search) . '"' : '' ?>.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($sections as $section): ?>
                        <div class="section-group">
                            <h4><?= htmlspecialchars($section['name']) ?> <span style="font-weight: 400; color: var(--muted); font-size: 12px;">(<?= $section['level_type'] ?> - <?= htmlspecialchars($section['program']) ?> - <?= htmlspecialchars($section['year_level']) ?>) · Capacity: <?= (int) $section['capacity'] ?> · Students: <?= (int) $section['student_count'] ?>/<?= (int) $section['capacity'] ?></span></h4>
                            <div class="table-wrapper" style="overflow-x: auto;">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Student Number</th>
                                            <th>Name</th>
                                            <th>Program</th>
                                            <th>Year Level</th>
                                            <th style="text-align: center;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        // Get students in this section
                                        $sectionStudents = [];
                                        try {
                                            $stmt = $pdo->prepare("SELECT s.id, s.student_number, u.full_name, s.program, s.year_level FROM students s JOIN users u ON s.user_id = u.id WHERE s.section_id = :sid ORDER BY u.full_name");
                                            $stmt->execute(['sid' => $section['id']]);
                                            $sectionStudents = $stmt->fetchAll();
                                        } catch (PDOException $e) {}
                                        ?>
                                        <?php if (empty($sectionStudents)): ?>
                                            <tr>
                                                <td colspan="5" style="text-align: center; padding: 20px; color: var(--muted);">No students assigned to this section.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($sectionStudents as $s): ?>
                                                <tr>
                                                    <td><strong><?= htmlspecialchars($s['student_number']) ?></strong></td>
                                                    <td><?= htmlspecialchars($s['full_name']) ?></td>
                                                    <td><?= htmlspecialchars($s['program']) ?></td>
                                                    <td><?= htmlspecialchars($s['year_level']) ?></td>
                                                    <td style="text-align: center;">
                                                        <form method="post" action="/registrar/process_section.php" style="display: inline;">
                                                            <input type="hidden" name="action" value="unassign_student">
                                                            <input type="hidden" name="student_id" value="<?= (int) $s['id'] ?>">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                            <button type="submit" class="btn-suspend" onclick="return confirm('Unassign this student from the section?');" style="padding: 4px 10px; font-size: 11px;">Unassign</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <!-- Assign Student Form -->
                            <?php if (count($sectionStudents) < (int)$section['capacity']): ?>
<form method="post" action="/registrar/process_section.php" style="margin-top: 12px; display: flex; gap: 8px; flex-wrap: wrap; align-items: end;">
                                <input type="hidden" name="action" value="assign_student">
                                <input type="hidden" name="section_id" value="<?= (int) $section['id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                    <div class="form-field" style="flex: 1; min-width: 200px;">
                                        <label for="student_id_<?= (int) $section['id'] ?>" class="eyebrow">Assign Student</label>
                                        <select id="student_id_<?= (int) $section['id'] ?>" name="student_id" required style="height: 40px;">
                                            <option value="">-- Select Student --</option>
                                            <?php foreach ($students as $st): ?>
                                                <option value="<?= (int) $st['id'] ?>"><?= htmlspecialchars($st['student_number'] . ' - ' . $st['full_name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <button type="submit" name="action" value="assign_student" class="button button-primary" style="height: 40px; font-size: 12px;">Assign</button>
                                </form>
                            <?php else: ?>
                                <p style="margin-top: 8px; font-size: 12px; color: var(--muted);">Section at full capacity (<?= (int) $section['capacity'] ?> students).</p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </article>
        </section>
    </main>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>