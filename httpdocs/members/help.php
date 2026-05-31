<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

$pageTitle = 'Uitleg & hulp — HB Foto & Video';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width:740px;">

    <div class="page-header">
        <h1>Uitleg &amp; hulp</h1>
    </div>
    <p class="text-muted" style="margin-bottom:2rem;">
        Kort overzicht van hoe het werkt. Kom je er niet uit? Neem contact op met
        HB Foto &amp; Video.
    </p>

    <section style="margin-bottom:2rem;">
        <h2>Video's bekijken</h2>
        <ol>
            <li>Ga naar <strong>Mijn video's</strong>. Hier staan de video's die
                voor jou beschikbaar zijn.</li>
            <li>Heb je een video nog niet gekocht, dan zie je de prijs en een knop
                om te <strong>kopen</strong>.</li>
            <li>Na betaling kun je de video direct <strong>online bekijken</strong>.
                Je hoeft niets te downloaden — afspelen kan zo vaak je wilt.</li>
        </ol>
    </section>

    <section style="margin-bottom:2rem;">
        <h2>Betalen</h2>
        <p>Betalen gaat veilig via <strong>Mollie</strong> (iDEAL, creditcard e.d.).
           Na het klikken op "Kopen" word je naar de betaalomgeving gestuurd en
           daarna automatisch teruggebracht naar de site. Zodra de betaling
           binnen is, staat de video voor je klaar.</p>
    </section>

    <section style="margin-bottom:2rem;">
        <h2>Toegangscode van een event</h2>
        <p>Heb je een <strong>toegangscode</strong> gekregen (bijvoorbeeld na een
           fotoshoot of evenement)? Dan horen daar speciale video's bij die je
           pas ziet nadat je de code invoert.</p>
        <ol>
            <li>Ga naar <strong>Mijn account</strong>.</li>
            <li>Vul de toegangscode in en bevestig.</li>
            <li>De bijbehorende video's verschijnen daarna bij
                <strong>Mijn video's</strong>.</li>
        </ol>
    </section>

    <section style="margin-bottom:2rem;">
        <h2>Wachtwoord vergeten</h2>
        <p>Op de inlogpagina staat een link <strong>"Wachtwoord vergeten?"</strong>.
           Vul je e-mailadres in; je ontvangt een e-mail met een link om een nieuw
           wachtwoord in te stellen. De link is 1 uur geldig.</p>
        <p class="text-muted" style="font-size:.9rem;">
            Geen e-mail ontvangen? Kijk ook in je spam-/ongewenste-mailmap.
        </p>
    </section>

    <section>
        <h2>Veelgestelde vragen</h2>
        <p><strong>Ik zie geen video's.</strong><br>
           Misschien zijn er nog geen video's voor jou klaargezet, of hoort je
           video bij een event waarvan je de toegangscode nog moet invoeren bij
           <a href="<?= BASE_URL ?>/members/account.php">Mijn account</a>.</p>

        <p><strong>Ik heb betaald maar zie de video niet.</strong><br>
           Soms duurt het verwerken van een betaling even. Ververs de pagina na
           een minuut. Blijft het misgaan? Neem contact op.</p>
    </section>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
