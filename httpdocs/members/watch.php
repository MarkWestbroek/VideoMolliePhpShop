<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/events.php';

requireLogin();

// Blokkeer videotoegang voor niet-geverifieerde gebruikers
if (!isEmailVerified()) {
    header('Location: ' . BASE_URL . '/members/?error=not_verified');
    exit;
}

$user    = currentUser();
$videoId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($videoId <= 0) {
    header('Location: ' . BASE_URL . '/members/');
    exit;
}

// Video ophalen
$stmt = db()->prepare('SELECT id, title, description, event_id, vimeo_id FROM videos WHERE id = ? AND active = 1' . ($user['is_admin'] ? '' : ' AND is_test = 0') . ' LIMIT 1');
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

// Log de weergave (één keer per sessie per video)
$viewKey = 'logged_view_' . $videoId;
if (empty($_SESSION[$viewKey])) {
    db()->prepare('INSERT INTO video_views (user_id, video_id) VALUES (?, ?)')
        ->execute([(int) $user['id'], $videoId]);
    $_SESSION[$viewKey] = true;
}

// Markeer als "aan het streamen" (voor admin-overzicht)
$_SESSION['streaming_video_id'] = $videoId;
$_SESSION['streaming_since']    = time();

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
    <?php if (!empty($video['vimeo_id'])): ?>
        <div style="position:relative;padding-top:56.25%;">
            <iframe src="https://player.vimeo.com/video/<?= htmlspecialchars($video['vimeo_id'], ENT_QUOTES, 'UTF-8') ?>?h=&badge=0&autopause=0&player_id=0&app_id=58479"
                    style="position:absolute;top:0;left:0;width:100%;height:100%;"
                    frameborder="0" allow="autoplay; fullscreen; picture-in-picture"
                    allowfullscreen title="<?= htmlspecialchars($video['title'], ENT_QUOTES, 'UTF-8') ?>">
            </iframe>
        </div>
    <?php else: ?>
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
    <?php endif; ?>
</div>

<script>
// Heartbeat: elke 30s streaming_at updaten zolang de video speelt
// Werkt voor zowel Vimeo (iframe) als lokale video's
(function() {
    var isPlaying = false;
    var timer     = null;
    var videoType = <?= json_encode(!empty($video['vimeo_id']) ? 'vimeo' : 'local') ?>;
    var heartbeatUrl = '<?= BASE_URL ?>/heartbeat.php?type=' + videoType;

    function sendHeartbeat() {
        fetch(heartbeatUrl, { method: 'POST', keepalive: true }).catch(function(){});
    }

    function startHeartbeat() {
        if (timer) return;
        isPlaying = true;
        sendHeartbeat();
        timer = setInterval(function() {
            if (isPlaying) sendHeartbeat();
        }, 30000);
    }

    function stopHeartbeat() {
        isPlaying = false;
        if (timer) { clearInterval(timer); timer = null; }
        // Stuur nog één heartbeat zodat admin weet dat stream nét gestopt is
        sendHeartbeat();
    }

    // Lokale video: HTML5 events
    var localVideo = document.querySelector('video');
    if (localVideo) {
        localVideo.addEventListener('play', startHeartbeat);
        localVideo.addEventListener('playing', startHeartbeat);
        localVideo.addEventListener('pause', stopHeartbeat);
        localVideo.addEventListener('ended', stopHeartbeat);
    }

    // Vimeo: postMessage API
    var vimeoIframe = document.querySelector('iframe[src*=\"player.vimeo.com\"]');
    if (vimeoIframe) {
        window.addEventListener('message', function(e) {
            if (e.origin !== 'https://player.vimeo.com') return;
            try {
                var data = JSON.parse(e.data);
                if (data.event === 'ready') {
                    // Vraag Vimeo om play/pause events te sturen
                    vimeoIframe.contentWindow.postMessage(JSON.stringify({method: 'addEventListener', value: 'play'}), 'https://player.vimeo.com');
                    vimeoIframe.contentWindow.postMessage(JSON.stringify({method: 'addEventListener', value: 'pause'}), 'https://player.vimeo.com');
                    vimeoIframe.contentWindow.postMessage(JSON.stringify({method: 'addEventListener', value: 'ended'}), 'https://player.vimeo.com');
                } else if (data.event === 'play') {
                    startHeartbeat();
                } else if (data.event === 'pause' || data.event === 'ended') {
                    stopHeartbeat();
                }
            } catch (ex) {}
        });
    }
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
