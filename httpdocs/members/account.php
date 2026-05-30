<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/events.php';

requireLogin();

$user    = currentUser();
$message = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $code   = trim($_POST['event_code'] ?? '');
    $result = redeemEventCode((int) $user['id'], $code);
    if ($result['ok']) {
        $message = $result['message'];
    } else {
        $error = $result['message'];
    }
}

if (($_GET['error'] ?? '') === 'no_access') {
    $error = 'Je hebt geen toegang tot deze video. Voer de toegangscode van het event in.';
}

// Events waartoe de gebruiker toegang heeft
$stmt = db()->prepare(
    'SELECT e.naam, e.organisator, ea.unlocked_at
     FROM event_access ea
     JOIN events e ON e.id = ea.event_id
     WHERE ea.user_id = ?
     ORDER BY ea.unlocked_at DESC'
);
$stmt->execute([(int) $user['id']]);
$myEvents = $stmt->fetchAll();

$pageTitle = 'Mijn account — HB Foto & Video';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Mijn account</h1>
</div>

<?php if ($message !== ''): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<div class="form-card" style="max-width:520px;margin:0 0 2rem;">
    <h2 style="font-size:1.1rem;margin-bottom:.75rem;">Event-toegangscode invoeren</h2>
    <p class="text-muted" style="margin-bottom:1rem;font-size:.9rem;">
        Heb je een toegangscode ontvangen van een event? Voer deze in om toegang te krijgen
        tot de video's van dat event.
    </p>
    <form method="post" action="<?= BASE_URL ?>/members/account.php">
        <?= csrfField() ?>
        <div class="form-group">
            <label for="event_code">Toegangscode</label>
            <input type="text" id="event_code" name="event_code" required maxlength="64" autofocus>
        </div>
        <button type="submit" class="btn btn-primary">Toegang ontgrendelen</button>
    </form>
</div>

<h2 style="font-size:1.1rem;margin-bottom:1rem;">Mijn events</h2>
<?php if (empty($myEvents)): ?>
    <p class="text-muted">Je hebt nog geen event-toegang. Voer hierboven een code in.</p>
<?php else: ?>
<div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr><th>Event</th><th>Organisator</th><th>Ontgrendeld op</th></tr>
        </thead>
        <tbody>
        <?php foreach ($myEvents as $e): ?>
            <tr>
                <td><?= htmlspecialchars($e['naam'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($e['organisator'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) $e['unlocked_at'], ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<p style="margin-top:1.5rem;">
    <a href="<?= BASE_URL ?>/members/" class="btn btn-primary">Naar mijn video's</a>
</p>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
