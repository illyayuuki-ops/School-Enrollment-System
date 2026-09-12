<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_role('admin');
require_once dirname(__DIR__) . '/db_connect.php';

$pdo = db_connect();

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

$settings = [
    'academic_year' => '2026-2027',
    'school_name' => 'Steiner Maris Academy',
    'registration_open' => '1',
];

try {
    $stmt = $pdo->query("SELECT `key`, `value` FROM app_settings");
    foreach ($stmt->fetchAll() as $row) {
        if (isset($settings[$row['key']])) {
            $settings[$row['key']] = $row['value'];
        }
    }
} catch (PDOException $e) {
    // Keep defaults on error
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = (string) ($_POST['csrf_token'] ?? '');
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        header('Location: /admin/settings.php?error=csrf');
        exit;
    }

    $academicYear = trim((string) ($_POST['academic_year'] ?? ''));
    $schoolName = trim((string) ($_POST['school_name'] ?? ''));
    $registrationOpen = isset($_POST['registration_open']) ? '1' : '0';

    if ($academicYear === '' || $schoolName === '') {
        header('Location: /admin/settings.php?error=validation');
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO app_settings (`key`, `value`) VALUES (:key, :value) ON CONFLICT (`key`) DO UPDATE SET `value` = EXCLUDED.`value`");

        $oldAcademicYear = $settings['academic_year'];
        $oldSchoolName = $settings['school_name'];
        $oldRegistrationOpen = $settings['registration_open'];

        $changes = [];
        if ($academicYear !== $oldAcademicYear) {
            $changes[] = "academic_year: {$oldAcademicYear} → {$academicYear}";
        }
        if ($schoolName !== $oldSchoolName) {
            $changes[] = "school_name: {$oldSchoolName} → {$schoolName}";
        }
        if ($registrationOpen !== $oldRegistrationOpen) {
            $changes[] = "registration_open: " . ($oldRegistrationOpen ? 'enabled' : 'disabled') . " → " . ($registrationOpen ? 'enabled' : 'disabled');
        }

        $stmt->execute(['key' => 'academic_year', 'value' => $academicYear]);
        $stmt->execute(['key' => 'school_name', 'value' => $schoolName]);
        $stmt->execute(['key' => 'registration_open', 'value' => $registrationOpen]);

        if (!empty($changes)) {
            $stmt = $pdo->prepare("INSERT INTO system_logs (actor_user_id, action, detail, created_at) VALUES (:actor, 'settings_changed', :detail, NOW())");
            $detail = "Settings updated: " . implode(', ', $changes);
            $stmt->execute(['actor' => $_SESSION['user']['id'], 'detail' => $detail]);
        }

        $pdo->commit();
        header('Location: /admin/settings.php?success=Settings+saved');
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        header('Location: /admin/settings.php?error=Database+error');
        exit;
    }
}

$pageTitle = 'Global Settings';
$extraStyles = ['/assets/admin.css'];
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="app-frame">
    <?php require dirname(__DIR__) . '/includes/sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div><span class="eyebrow">Administrator workspace</span><h1>Welcome, <?= htmlspecialchars(explode(' ', $_SESSION['user']['full_name'])[0], ENT_QUOTES, 'UTF-8') ?>.</h1></div>
            <div class="topbar-actions"><span class="year-pill">A.Y. 2026–2027</span><a class="avatar" href="#" onclick="return openLogoutModal()" title="Sign out"><?= strtoupper(substr((string) $_SESSION['user']['full_name'], 0, 1)) ?></a></div>
        </header>
        <section class="content-grid">
            <article class="panel panel-wide">
                <div class="panel-heading"><div><span class="eyebrow">Configuration</span><h2>Global Settings</h2></div></div>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="post" action="/admin/settings.php" class="settings-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                    <div class="form-grid">
                        <div class="form-field">
                            <label for="academic_year">Academic Year *</label>
                            <input type="text" id="academic_year" name="academic_year" required value="<?= htmlspecialchars($settings['academic_year']) ?>" placeholder="e.g. 2026-2027" style="height: 40px;">
                        </div>
                        <div class="form-field">
                            <label for="school_name">School Name *</label>
                            <input type="text" id="school_name" name="school_name" required value="<?= htmlspecialchars($settings['school_name']) ?>" placeholder="e.g. Steiner Maris Academy" style="height: 40px;">
                        </div>
                        <div class="form-field" style="align-items: flex-end;">
                            <label for="registration_open">Registration Open</label>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <input type="checkbox" id="registration_open" name="registration_open" value="1" <?= $settings['registration_open'] === '1' ? 'checked' : '' ?>>
                                <label for="registration_open" style="margin: 0; font-weight: 500; font-size: 13px;">Enable public registration</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-save">Save Settings</button>
                    </div>
                </form>

                <div style="margin-top: 24px; padding: 16px; background: #f8faf9; border: 1px solid var(--line); border-radius: var(--radius);">
                    <h4 style="margin: 0 0 12px; font-size: 13px; font-weight: 700; color: var(--navy);">Current Values</h4>
                    <dl style="margin: 0; display: grid; grid-template-columns: 200px 1fr; gap: 8px 16px; font-size: 13px;">
                        <dt style="color: var(--muted); font-weight: 500;">Academic Year</dt>
                        <dd style="margin: 0; font-weight: 600; color: var(--ink);"><?= htmlspecialchars($settings['academic_year']) ?></dd>
                        <dt style="color: var(--muted); font-weight: 500;">School Name</dt>
                        <dd style="margin: 0; font-weight: 600; color: var(--ink);"><?= htmlspecialchars($settings['school_name']) ?></dd>
                        <dt style="color: var(--muted); font-weight: 500;">Registration Open</dt>
                        <dd style="margin: 0; font-weight: 600; color: var(--ink);"><?= $settings['registration_open'] === '1' ? 'Yes' : 'No' ?></dd>
                    </dl>
                </div>
            </article>
        </section>
    </main>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>