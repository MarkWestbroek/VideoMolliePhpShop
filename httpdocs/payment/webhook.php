<?php
declare(strict_types=1);

/**
 * webhook.php — Mollie betaalstatus callback
 *
 * Mollie POST-t een 'id' parameter zodra de betalingstatus wijzigt.
 * Wij vragen daarna de actuele status op via de API.
 *
 * BELANGRIJK: dit endpoint moet bereikbaar zijn via HTTPS (al ingesteld).
 * Geen output behalve HTTP 200 (anders probeert Mollie het opnieuw).
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../vendor/autoload.php';

// Mollie stuurt de betaal-ID via POST
$paymentId = $_POST['id'] ?? '';

if ($paymentId === '') {
    http_response_code(400);
    exit;
}

// Haal actuele betalingsstatus op bij Mollie (vertrouw nooit alleen de POST)
try {
    $mollie = new \Mollie\Api\MollieApiClient();
    $mollie->setApiKey(MOLLIE_API_KEY);
    $payment = $mollie->payments->get($paymentId);
} catch (\Mollie\Api\Exceptions\ApiException $e) {
    error_log('Mollie webhook API fout: ' . $e->getMessage());
    // Geef toch 200 terug zodat Mollie niet eindeloos herprobeert
    http_response_code(200);
    exit;
}

// Vertaal Mollie-status naar onze ENUM
if ($payment->isPaid()) {
    $newStatus = 'paid';
} elseif ($payment->isExpired()) {
    $newStatus = 'expired';
} elseif ($payment->isFailed()) {
    $newStatus = 'failed';
} elseif ($payment->isCanceled()) {
    $newStatus = 'canceled';
} elseif ($payment->isPending()) {
    $newStatus = 'pending';
} else {
    $newStatus = 'open';
}

$paidAt = $payment->isPaid() ? date('Y-m-d H:i:s') : null;

// Bijwerken in de database
// Bescherming: stel 'paid' niet terug naar iets anders
try {
    if ($payment->isPaid()) {
        $stmt = db()->prepare(
            "UPDATE purchases
             SET status = 'paid', paid_at = ?
             WHERE mollie_payment_id = ? AND status != 'paid'"
        );
        $stmt->execute([$paidAt, $paymentId]);
    } else {
        $stmt = db()->prepare(
            "UPDATE purchases
             SET status = ?
             WHERE mollie_payment_id = ? AND status != 'paid'"
        );
        $stmt->execute([$newStatus, $paymentId]);
    }
} catch (\PDOException $e) {
    error_log('Mollie webhook DB fout: ' . $e->getMessage());
}

http_response_code(200);
exit;
