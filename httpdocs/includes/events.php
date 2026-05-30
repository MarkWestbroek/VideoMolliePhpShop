<?php
declare(strict_types=1);

/**
 * Hulpkfuncties voor event-toegang (privacy / event-bundels).
 *
 * Vereist dat config.php en db.php al geladen zijn.
 */

/**
 * Geef de event-id's waartoe een gebruiker toegang heeft.
 *
 * @return int[]
 */
function getUserEventIds(int $userId): array
{
    $stmt = db()->prepare('SELECT event_id FROM event_access WHERE user_id = ?');
    $stmt->execute([$userId]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Controleer of een gebruiker toegang heeft tot een specifiek event.
 */
function userHasEventAccess(int $userId, int $eventId): bool
{
    $stmt = db()->prepare(
        'SELECT 1 FROM event_access WHERE user_id = ? AND event_id = ? LIMIT 1'
    );
    $stmt->execute([$userId, $eventId]);
    return (bool) $stmt->fetchColumn();
}

/**
 * Verzilver een toegangscode voor een gebruiker.
 *
 * @return array{ok: bool, message: string, event: ?array}
 */
function redeemEventCode(int $userId, string $code): array
{
    $code = trim($code);
    if ($code === '') {
        return ['ok' => false, 'message' => 'Voer een toegangscode in.', 'event' => null];
    }

    $stmt = db()->prepare(
        'SELECT id, naam FROM events WHERE toegangscode = ? AND active = 1 LIMIT 1'
    );
    $stmt->execute([$code]);
    $event = $stmt->fetch();

    if (!$event) {
        return ['ok' => false, 'message' => 'Ongeldige of niet langer geldige toegangscode.', 'event' => null];
    }

    try {
        db()->prepare('INSERT INTO event_access (user_id, event_id) VALUES (?, ?)')
            ->execute([$userId, (int) $event['id']]);
    } catch (\PDOException $e) {
        // Dubbele invoer => gebruiker had al toegang
        return [
            'ok'      => true,
            'message' => 'Je had al toegang tot het event: ' . $event['naam'] . '.',
            'event'   => $event,
        ];
    }

    return [
        'ok'      => true,
        'message' => 'Toegang verleend tot het event: ' . $event['naam'] . '.',
        'event'   => $event,
    ];
}
