<?php
/**
 * HALAMAN AI STUDY CHAT
 * Chat-nya adalah aplikasi React terpisah di folder ai-chat/.
 * Halaman ini hanya menampilkan hasil build-nya (ai-chat/dist) di dalam <iframe>.
 */
require_once __DIR__ . '/includes/init.php';
requireLogin();

$today = getToday($conn);

$activePage = 'chat';
$pageTitle = 'AI study chat';
$pageSubtitle = 'Ask with AI';
require __DIR__ . '/includes/header.php';
?>

<section class="chat-page">
    <iframe
        class="chat-app-frame"
        src="<?= e(asset('ai-chat/dist/index.html')) ?>"
        title="AI study coach"
        loading="eager"
    ></iframe>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
