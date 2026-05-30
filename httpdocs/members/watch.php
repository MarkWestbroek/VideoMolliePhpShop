<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/events.php';

requireLogin();

$user    = currentUser();
$videoId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($videoId <= 0) {
    header('Location: ' . BASE_URL . '/members/');
    exit;
}

// Video ophalen
$stmt = db()->prepare('SELECT id, title, description, event_id FROM videos WHERE id = ? AND active = 1 LIMIT 1');
$stmt->execute([$videoId]);
$video = $stmt->fetch();

if (!$video) {
    header('Location: ' . BASE_URL . '/members/');
    exit;
}

// Privacy-controle: bij een event-video moet de gebruiker toegang hebben
if (!empty($video['event_id']) && !userHasEventAccess((int) $user['id'], (int) $video['event_id'])) {
    header('Location: ' . BASE_URL . '/members/');
    exit;
}

// Toegangscontrole: heeft de gebruiker betaald?
if (!hasPurchased((int) $user['id'], $videoId)) {
    header('Location: ' . BASE_URL . '/members/');
    exit;
}

$pageTitle = htmlspecialchars($video['title'], ENT_QUOTES, 'UTF-8') . ' — HB Foto & Video';
require_once __DIR__ . '/../includes/header.php';
?>

<a href="<?= BASE_URL ?>/members/" class="back-link">&larr; Terug naar overzicht</a>

<div class="video-meta">
    <h1><?= htmlspecialchars($video['title'], ENT_QUOTES, 'UTF-8') ?></h1>
    <?php if ($video['description']): ?>
        <p><?= nl2br(htmlspecialchars($video['description'], ENT_QUOTES, 'UTF-8')) ?></p>
    <?php endif; ?>
</div>

<div class="player-wrap">
    <video
        controls
        controlsList="nodownload noremoteplayback"
        disablePictureInPicture
        preload="metadata"
        playsinline
        oncontextmenu="return false;"
        style="max-height: 70vh;"
    >
        <source src="<?= BASE_URL ?>/stream.php?id=<?= (int) $video['id'] ?>" type="video/mp4">
        <p>Jouw browser ondersteunt geen HTML5-video.</p>
    </video>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
