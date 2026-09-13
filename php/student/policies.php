<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_role('student');
require_once dirname(__DIR__) . '/db_connect.php';
require_once dirname(__DIR__) . '/includes/settings.php';

$pdo = db_connect();
$studentId = (int) ($_SESSION['user']['id'] ?? 0);

$academicYear = get_academic_year($pdo);

$documents = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM academic_policy_documents
        WHERE academic_year = :ay
        ORDER BY title
    ");
    $stmt->execute(['ay' => $academicYear]);
    $documents = $stmt->fetchAll();
} catch (PDOException $e) {
    // Keep defaults on error
}

$pageTitle = 'Academic Policies';
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
                <div class="panel-heading"><div><span class="eyebrow">Academic</span><h2>Academic Policies</h2></div></div>

                <?php if (empty($documents)): ?>
                    <div class="empty-state">
                        <p>No academic policy documents available for this academic year.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($documents as $doc): ?>
                        <div class="detail-section" style="margin-bottom: 24px;">
                            <h4><?= htmlspecialchars($doc['title']) ?></h4>
                            <div class="detail-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 16px;">
                                <div class="detail-field">
                                    <label>Academic Year</label>
                                    <div class="value"><?= htmlspecialchars($doc['academic_year']) ?></div>
                                </div>
                                <div class="detail-field">
                                    <label>Page Count</label>
                                    <div class="value"><?= (int) $doc['page_count'] ?> pages</div>
                                </div>
                                <div class="detail-field">
                                    <label>File</label>
                                    <div class="value"><?= htmlspecialchars($doc['file_name']) ?></div>
                                </div>
                            </div>
                            <div style="margin-top: 12px;">
                                <strong style="color: var(--muted); font-size: 11px; text-transform: uppercase; letter-spacing: .05em;">Summary</strong>
                                <p style="margin-top: 4px; color: var(--ink); font-size: 13px; line-height: 1.6;"><?= nl2br(htmlspecialchars($doc['summary'])) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </article>
        </section>
    </main>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>