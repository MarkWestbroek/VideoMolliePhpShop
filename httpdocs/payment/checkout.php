<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/events.php';

requireLogin();
verifyCsrf();

$user    = currentUser();
$videoId = isset($_POST['video_id']) ? (int) $_POST['video_id'] : 0;

if ($videoId <= 0) {
    header('Location: ' . BASE_URL . '/members/');
    exit;
}

// Video ophalen (inclusief staffel en event)
$stmt = db()->prepare('SELECT id, title, price, staffel_id, event_id FROM videos WHERE id = ? AND active = 1 LIMIT 1');
$stmt->execute([$videoId]);
$video = $stmt->fetch();

if (!$video) {
    header('Location: ' . BASE_URL . '/members/');
    exit;
}

// Privacy-controle: bij een event-video moet de gebruiker toegang hebben
if (!empty($video['event_id']) && !userHasEventAccess((int) $user['id'], (int) $video['event_id'])) {
    header('Location: ' . BASE_URL . '/members/account.php?error=no_access');
    exit;
}

// Al betaald? Direct doorsturen naar watch-pagina
$stmt = db()->prepare("SELECT status FROM purchases WHERE user_id = ? AND video_id = ? LIMIT 1");
$stmt->execute([$user['id'], $videoId]);
$existing = $stmt->fetch();

if ($existing && $existing['status'] === 'paid') {
    header('Location: ' . BASE_URL . '/members/watch.php?id=' . $videoId);
    exit;
}

// Bereken te betalen prijs (staffel of vast)
$staffelId = (int) ($video['staffel_id'] ?? 0);
if ($staffelId > 0) {
    // Tel hoeveel video's van dezelfde staffel al betaald zijn
    $stmt2 = db()->prepare(
        "SELECT COUNT(*) FROM purchases p
         JOIN videos v ON v.id = p.video_id
         WHERE p.user_id = ? AND p.status = 'paid' AND v.staffel_id = ?"
    );
    $stmt2->execute([$user['id'], $staffelId]);
    $alGekocht = (int) $stmt2->fetchColumn();

    $volgend = $alGekocht + 1;
    $stmt3 = db()->prepare(
        'SELECT prijs FROM staffelprijzen
         WHERE staffel_id = ? AND aantal_van <= ? AND aantal_tot >= ?
         ORDER BY aantal_van DESC LIMIT 1'
    );
    $stmt3->execute([$staffelId, $volgend, $volgend]);
    $trapRow = $stmt3->fetch();
    $berekendeprijs = $trapRow ? (float) $trapRow['prijs'] : (float) $video['price'];
} else {
    $berekendeprijs = (float) $video['price'];
}

// Mollie betaling aanmaken
require_once __DIR__ . '/../vendor/autoload.php';

$mollie = new \Mollie\Api\MollieApiClient();
$mollie->setApiKey(MOLLIE_API_KEY);

$price  = number_format($berekendeprijs, 2, '.', '');
$userId = (int) $user['id'];

try {
    $payment = $mollie->payments->create([
        'amount'      => [
            'currency' => 'EUR',
            'value'    => $price,
        ],
        'description' => 'Toegang tot: ' . $video['title'],
        'redirectUrl' => BASE_URL . '/payment/return.php?video_id=' . $videoId,
        'webhookUrl'  => BASE_URL . '/payment/webhook.php',
        'metadata'    => [
            'user_id'  => $userId,
            'video_id' => $videoId,
        ],
    ]);
} catch (\Mollie\Api\Exceptions\ApiException $e) {
    error_log('Mollie API fout: ' . $e->getMessage());
    header('Location: ' . BASE_URL . '/members/?error=payment');
    exit;
}

// Aankoop opslaan of bijwerken in de database
if ($existing) {
    // Bijwerken (status was niet 'paid', dus veilig om te overschrijven)
    $stmt = db()->prepare(
        "UPDATE purchases SET mollie_payment_id = ?, status = 'open', amount = ?
         WHERE user_id = ? AND video_id = ?"
    );
    $stmt->execute([$payment->id, $berekendeprijs, $userId, $videoId]);
} else {
    $stmt = db()->prepare(
        "INSERT INTO purchases (user_id, video_id, mollie_payment_id, status, amount)
         VALUES (?, ?, ?, 'open', ?)"
    );
    $stmt->execute([$userId, $videoId, $payment->id, $berekendeprijs]);
}

// Doorsturen naar Mollie betaalpagina
header('Location: ' . $payment->getCheckoutUrl());
exit;
