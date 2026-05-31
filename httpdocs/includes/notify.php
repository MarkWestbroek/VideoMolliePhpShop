<?php
declare(strict_types=1);

/**
 * Berekent een verdachtheid-score voor een nieuwe registratie.
 * Score >= 3 → markeer als verdacht.
 *
 * Signalen:
 *  +3  Naam heeft < 20% klinkers (bv. "xwsnhzfldo")
 *  +2  Naam heeft < 30% klinkers
 *  +1  Naam heeft geen spaties en is volledig lowercase
 *  +2  E-mail-gebruikersdeel heeft < 20% klinkers (bv. "qimehmnn")
 *  +1  E-mail-domein staat op de bekende wegwerp-lijst
 */
function registrationSuspicionScore(string $name, string $email): int
{
    $score = 0;

    // --- Klinker-ratio naam ---
    $letters = preg_replace('/[^a-zA-Z]/', '', $name);
    $len     = strlen($letters);
    if ($len >= 6) {
        $vowels = preg_match_all('/[aeiouAEIOU]/', $letters);
        $ratio  = $vowels / $len;
        if ($ratio < 0.20)      $score += 3;
        elseif ($ratio < 0.30)  $score += 2;
    }

    // --- Naam: geen spatie + volledig lowercase ---
    if (strpos($name, ' ') === false && $name === strtolower($name)) {
        $score += 1;
    }

    // --- Klinker-ratio e-mail gebruikersdeel ---
    $emailUser = strtolower(explode('@', $email)[0] ?? '');
    $emailLetters = preg_replace('/[^a-z]/', '', $emailUser);
    $eLen = strlen($emailLetters);
    if ($eLen >= 4) {
        $eVowels = preg_match_all('/[aeiou]/', $emailLetters);
        if ($eLen > 0 && ($eVowels / $eLen) < 0.20) {
            $score += 2;
        }
    }

    // --- Bekende wegwerp-domeinen ---
    $disposable = [
        'mailinator.com', 'guerrillamail.com', 'guerrillamail.net',
        'trashmail.com', 'trashmail.net', 'trashmail.me',
        'tempmail.com', 'temp-mail.org', 'yopmail.com',
        'sharklasers.com', 'guerrillamailblock.com', 'grr.la',
        'spam4.me', 'dispostable.com', 'maildrop.cc',
        'throwam.com', 'throwaway.email', 'fakeinbox.com',
        'immenseignite.info', 'spamgourmet.com', 'spamgourmet.net',
        'mailnull.com', 'spamex.com', 'deadaddress.com',
    ];
    $domain = strtolower(explode('@', $email)[1] ?? '');
    if (in_array($domain, $disposable, true)) {
        $score += 1;
    }

    return $score;
}

/**
 * Stuurt een notificatie-e-mail naar alle admins bij een nieuwe registratie.
 * Markeert de mail als verdacht als score >= 3.
 */
function notifyAdminsNewUser(int $newUserId, string $name, string $email): void
{
    // Haal alle admin e-mailadressen op
    $stmt = db()->prepare('SELECT email, name FROM users WHERE is_admin = 1');
    $stmt->execute();
    $admins = $stmt->fetchAll();

    if (empty($admins)) {
        return;
    }

    $score     = registrationSuspicionScore($name, $email);
    $isSuspect = $score >= 3;

    $domain    = parse_url(BASE_URL, PHP_URL_HOST) ?? 'hbfoto.nl';
    $adminUrl  = BASE_URL . '/admin/users.php';

    $label   = $isSuspect ? '⚠️ VERDACHTE registratie' : 'Nieuwe registratie';
    $subject = $label . ' — HB Foto & Video';

    $suspectBlock = '';
    if ($isSuspect) {
        $suspectBlock = "\r\n"
            . "*** MOGELIJK BOT / SPAM (verdachtheid-score: {$score}) ***\r\n"
            . "Signalen: willekeurig-ogende naam en/of e-mail, wegwerp-domein.\r\n"
            . "Controleer de gebruiker en trek indien nodig de toegang in.\r\n";
    }

    $body = "Hallo,\r\n\r\n"
          . "Er heeft zich zojuist een nieuw account aangemeld op HB Foto & Video.\r\n"
          . $suspectBlock
          . "\r\n"
          . "ID    : {$newUserId}\r\n"
          . "Naam  : {$name}\r\n"
          . "E-mail: {$email}\r\n"
          . "\r\n"
          . "Beheer gebruikers: {$adminUrl}\r\n"
          . "\r\n"
          . "— HB Foto & Video (automatisch bericht)";

    $headers = implode("\r\n", [
        'From: HB Foto & Video <noreply@' . $domain . '>',
        'Reply-To: noreply@' . $domain,
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: PHP/' . PHP_VERSION,
    ]);

    foreach ($admins as $admin) {
        mail($admin['email'], $subject, $body, $headers);
    }
}
