<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_role('student');
require_once dirname(__DIR__) . '/db_connect.php';
require_once dirname(__DIR__) . '/includes/settings.php';

$pdo = db_connect();
$studentId = (int) ($_SESSION['user']['id'] ?? 0);

$academicYear = get_academic_year($pdo);

$faqs = [];
try {
    $stmt = $pdo->prepare("
        SELECT question, answer, display_order
        FROM faq_entries
        WHERE active = TRUE
        ORDER BY display_order
    ");
    $stmt->execute();
    $faqs = $stmt->fetchAll();
} catch (PDOException $e) {
    // Keep defaults on error
}

$pageTitle = 'FAQ';
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
                <div class="panel-heading"><div><span class="eyebrow">Help</span><h2>Frequently Asked Questions</h2></div></div>

                <?php if (empty($faqs)): ?>
                    <div class="empty-state">
                        <p>No FAQs available at the moment.</p>
                    </div>
                <?php else: ?>
                    <div style="display: grid; gap: 16px;">
                        <?php foreach ($faqs as $faq): ?>
                            <details class="faq-item" style="background: #fff; border: 1px solid var(--line); border-radius: var(--radius); padding: 20px;">
                                <summary style="cursor: pointer; font-weight: 600; color: var(--navy); font-size: 14px; list-style: none; display: flex; justify-content: space-between; align-items: center;">
                                    <span><?= htmlspecialchars($faq['question']) ?></span>
                                    <span style="font-size: 18px; color: var(--muted); transition: transform 0.2s;">▼</span>
                                </summary>
                                <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--line); color: var(--ink); line-height: 1.6;">
                                    <?= nl2br(htmlspecialchars($faq['answer'])) ?>
                                </div>
                            </details>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </article>
        </section>
    </main>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>