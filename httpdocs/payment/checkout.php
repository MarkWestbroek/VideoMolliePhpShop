<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

requireLogin();
verifyCsrf();

$user    = currentUser();
$videoId = isset($_POST['video_id']) ? (int) $_POST['video_id'] : 0;

if ($videoId <= 0) {
    header('Location: ' . BASE_URL . '/members/');
    exit;
}

// Video ophalen
$stmt = db()->prepare('SELECT id, title, price FROM videos WHERE id = ? AND active = 1 LIMIT 1');
$stmt->execute([$videoId]);
$video = $stmt->fetch();

if (!$video) {
    header('Location: ' . BASE_URL . '/members/');
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

// Mollie betaling aanmaken
require_once __DIR__ . '/../vendor/autoload.php';

$mollie = new \Mollie\Api\MollieApiClient();
$mollie->setApiKey(MOLLIE_API_KEY);

$price  = number_format((float) $video['price'], 2, '.', '');
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
    $stmt->execute([$payment->id, $video['price'], $userId, $videoId]);
} else {
    $stmt = db()->prepare(
        "INSERT INTO purchases (user_id, video_id, mollie_payment_id, status, amount)
         VALUES (?, ?, ?, 'open', ?)"
    );
    $stmt->execute([$userId, $videoId, $payment->id, $video['price']]);
}

// Doorsturen naar Mollie betaalpagina
header('Location: ' . $payment->getCheckoutUrl());
exit;
