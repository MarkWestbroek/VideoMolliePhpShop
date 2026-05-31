<?php
function registrationSuspicionScore(string $name, string $email): int {
    $score = 0;
    $letters = preg_replace('/[^a-zA-Z]/', '', $name);
    $len = strlen($letters);
    if ($len >= 4) {
        $vowels = preg_match_all('/[aeiouAEIOU]/', $letters);
        $ratio = $vowels / $len;
        if ($ratio < 0.20)     $score += 3;
        elseif ($ratio < 0.30) $score += 2;
    }
    if (strpos($name, ' ') === false && $name === strtolower($name)) $score += 1;
    $emailUser = strtolower(explode('@', $email)[0] ?? '');
    $emailLetters = preg_replace('/[^a-z]/', '', $emailUser);
    $eLen = strlen($emailLetters);
    if ($eLen >= 4) {
        $eVowels = preg_match_all('/[aeiou]/', $emailLetters);
        if ($eLen > 0 && ($eVowels / $eLen) < 0.20) $score += 2;
    }
    $disposable = ['mailinator.com','guerrillamail.com','trashmail.com','tempmail.com',
        'yopmail.com','immenseignite.info','maildrop.cc','throwaway.email','fakeinbox.com'];
    $domain = strtolower(explode('@', $email)[1] ?? '');
    if (in_array($domain, $disposable, true)) $score += 1;
    return $score;
}

$cases = [
    ['xwsnhzfldo',    'qimehmnn@immenseignite.info',  'De bot uit het scherm'],
    ['Jan de Vries',  'jan.devries@hotmail.com',       'Normale gebruiker'],
    ['hansbremerman', 'hansbremerman@hotmail.com',     'Hans (geen spatie, maar klinkers OK)'],
    ['ztxkplmvqr',   'abc@mailinator.com',             'Duidelijke bot'],
    ['TestBot99',    'xkqzjnvb@guerrillamail.com',     'Bot met cijfers in naam'],
    ['mark',         'westbroek.mark@gmail.com',       'Mark (korte naam, lowercase, geen spatie)'],
];

printf("%-20s %-35s %-8s %s\n", 'Naam', 'E-mail', 'Score', 'Oordeel');
echo str_repeat('-', 85) . "\n";
foreach ($cases as $c) {
    [$name, $email, $label] = $c;
    $score = registrationSuspicionScore($name, $email);
    $verdict = $score >= 3 ? 'VERDACHT' : 'OK';
    printf("%-20s %-35s %-8d %-10s (%s)\n", $name, $email, $score, $verdict, $label);
}
