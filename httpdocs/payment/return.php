<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

$videoId = isset($_GET['video_id']) ? (int) $_GET['video_id'] : 0;
$user    = currentUser();

// Status van de aankoop ophalen
$status = null;
if ($videoId > 0) {
    $stmt = db()->prepare("SELECT status FROM purchases WHERE user_id = ? AND video_id = ? LIMIT 1");
    $stmt->execute([$user['id'], $videoId]);
    $row    = $stmt->fetch();
    $status = $row['status'] ?? null;
}

$pageTitle = 'Betaling — HB Foto & Video';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width:560px; margin:2rem auto; text-align:center;">

<?php if ($status === 'paid'): ?>

    <div class="alert alert-success">
        <strong>Betaling geslaagd!</strong><br>
        Je hebt nu toegang tot de video.
    </div>
    <a href="<?= BASE_URL ?>/members/watch.php?id=<?= $videoId ?>" class="btn btn-success">
        &#9654; Video bekijken
    </a>

<?php elseif (in_array($status, ['open', 'pending'], true)): ?>

    <div class="alert alert-info">
        <strong>Betaling wordt verwerkt&hellip;</strong><br>
        Dit kan even duren. Ververs de pagina of keer terug naar het overzicht.
    </div>
    <p class="text-muted" style="margin-bottom:1rem; font-size:.9rem">
        Zodra de betaling bevestigd is, verschijnt de video in je overzicht.
    </p>
    <a href="<?= BASE_URL ?>/members/" class="btn btn-secondary">Terug naar overzicht</a>

<?php elseif (in_array($status, ['failed', 'expired', 'canceled'], true)): ?>

    <div class="alert alert-error">
        <strong>Betaling niet geslaagd.</strong><br>
        Je kunt het opnieuw proberen via het videooverzicht.
    </div>
    <a href="<?= BASE_URL ?>/members/" class="btn btn-primary">Terug naar overzicht</a>

<?php else: ?>

    <div class="alert alert-error">Onbekende betaalstatus. Ga terug naar het overzicht.</div>
    <a href="<?= BASE_URL ?>/members/" class="btn btn-secondary">Terug naar overzicht</a>

<?php endif; ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
