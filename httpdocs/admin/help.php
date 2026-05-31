<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

$pageTitle = 'Handleiding — HB Foto & Video';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Navigatie tabs (zelfde patroon als de andere admin-pagina's) -->
<nav style="display:flex;gap:.75rem;margin-bottom:1.75rem;border-bottom:1px solid var(--border);padding-bottom:.75rem;flex-wrap:wrap;">
    <a href="<?= BASE_URL ?>/admin/"            class="btn btn-sm btn-secondary">Video's</a>
    <a href="<?= BASE_URL ?>/admin/users.php"    class="btn btn-sm btn-secondary">Gebruikers</a>
    <a href="<?= BASE_URL ?>/admin/staffels.php" class="btn btn-sm btn-secondary">&#9654; Staffels</a>
    <a href="<?= BASE_URL ?>/admin/events.php"   class="btn btn-sm btn-secondary">&#9654; Events</a>
    <a href="<?= BASE_URL ?>/admin/help.php"     class="btn btn-sm btn-primary">&#9432; Handleiding</a>
</nav>

<div style="max-width:820px;">

    <div class="page-header">
        <h1>Handleiding voor beheerders</h1>
    </div>
    <p class="text-muted" style="margin-bottom:2rem;">
        Deze pagina legt stap voor stap uit hoe je de site beheert: video's
        toevoegen, prijzen instellen, events maken en gebruikers beheren.
        Je hebt geen technische kennis nodig.
    </p>

    <!-- ============================================================ -->
    <section style="margin-bottom:2.5rem;">
        <h2>1. Een video toevoegen</h2>
        <p>Een video bestaat uit <strong>twee delen</strong>: het videobestand
           (dat je via FTP uploadt) en de gegevens in de site (titel, prijs).</p>

        <h3 style="font-size:1.05rem;margin-top:1.25rem;">Stap A — Videobestand uploaden via FTP</h3>
        <ol>
            <li>Open je FTP-programma (bijv. FileZilla) en verbind met de server.</li>
            <li>Upload het videobestand (bijv. <code>les1.mp4</code>) naar de map
                <strong>private/videos</strong>. <em>Let op:</em> dit is een aparte
                map <strong>buiten</strong> de website-map — video's mogen daar staan
                zodat ze niet zomaar te downloaden zijn.</li>
            <li>Onthoud de exacte bestandsnaam, inclusief <code>.mp4</code>.
                Gebruik liefst namen zonder spaties of vreemde tekens.</li>
        </ol>

        <h3 style="font-size:1.05rem;margin-top:1.25rem;">Stap B — Video aanmaken in de site</h3>
        <ol>
            <li>Ga naar <strong>Beheer &rarr; Video's &rarr; + Video toevoegen</strong>.</li>
            <li>Vul de <strong>titel</strong> en eventueel een omschrijving in.</li>
            <li>Vul bij <strong>bestandsnaam</strong> exact de naam in die je
                geüpload hebt (bijv. <code>les1.mp4</code>).</li>
            <li>Kies een <strong>prijs</strong> óf een <strong>staffel</strong>
                (zie punt 2 hieronder).</li>
            <li>Wil je de video besloten houden? Kies een <strong>event</strong>
                (zie punt 3). Anders laat je dit op "Openbaar" staan.</li>
            <li>Klik op <strong>Opslaan</strong>.</li>
        </ol>
        <p class="text-muted" style="font-size:.9rem;">
            Tip: zet een video op <em>inactief</em> (via Bewerken) als je hem
            tijdelijk wilt verbergen zonder hem te verwijderen.
        </p>
    </section>

    <!-- ============================================================ -->
    <section style="margin-bottom:2.5rem;">
        <h2>2. Prijzen &amp; staffels</h2>
        <p>Er zijn twee manieren om een prijs te bepalen:</p>
        <ul>
            <li><strong>Vaste prijs</strong> — één bedrag voor de video.</li>
            <li><strong>Staffel</strong> — de prijs daalt naarmate een klant méér
                video's uit dezelfde groep koopt. Bijvoorbeeld: 1e video €10,
                2e en 3e €8,75, vanaf de 4e €7,50.</li>
        </ul>
        <p>Een staffel maak je aan onder <strong>Beheer &rarr; Staffels</strong>:</p>
        <ol>
            <li>Maak een staffel aan met een herkenbare naam (bijv. "Cursus voorjaar").</li>
            <li>Voeg <strong>prijstrappen</strong> toe: "van het hoeveelste t/m het
                hoeveelste = welke prijs". Bijv. van 1 t/m 1 = €10, van 2 t/m 3 = €8,75,
                van 4 t/m 999 = €7,50.</li>
            <li>Koppel daarna video's aan deze staffel via het video-formulier.</li>
        </ol>
        <p class="text-muted" style="font-size:.9rem;">
            Kies je een staffel bij een video, dan hoeft het vaste prijsveld niet
            ingevuld te worden (het dient alleen als terugval).
        </p>
    </section>

    <!-- ============================================================ -->
    <section style="margin-bottom:2.5rem;">
        <h2>3. Events (besloten video's)</h2>
        <p>Een <strong>event</strong> gebruik je als bepaalde video's alleen
           zichtbaar mogen zijn voor een specifieke groep mensen — bijvoorbeeld
           de deelnemers van een fotoshoot. Zij krijgen een
           <strong>toegangscode</strong>.</p>
        <ol>
            <li>Ga naar <strong>Beheer &rarr; Events</strong> en maak een nieuw event aan
                (naam, organisator, eventueel omschrijving).</li>
            <li>Laat het codeveld leeg om automatisch een code te laten genereren,
                of vul zelf een code in.</li>
            <li>Koppel video's aan dit event via het video-formulier (veld "Event").</li>
            <li>Geef de toegangscode aan de betrokken klanten. Zij voeren die in op
                hun accountpagina en zien daarna de bijbehorende video's.</li>
        </ol>
        <p class="text-muted" style="font-size:.9rem;">
            Video's met een event zijn voor iedereen anders <strong>onzichtbaar</strong>
            — niet "zichtbaar maar op slot". Zonder code bestaan ze simpelweg niet
            voor die bezoeker.
        </p>
    </section>

    <!-- ============================================================ -->
    <section style="margin-bottom:2.5rem;">
        <h2>4. Gebruikers beheren</h2>
        <p>Onder <strong>Beheer &rarr; Gebruikers</strong> zie je alle accounts.</p>
        <ul>
            <li><strong>Online</strong> — wie de laatste 15 minuten actief was.</li>
            <li><strong>Events / Aankopen / Betaald</strong> — hoeveel events een
                klant heeft ontgrendeld, hoeveel video's gekocht, en het totaalbedrag.</li>
            <li><strong>Details</strong> — klap een gebruiker open om precies te zien
                welke events en aankopen erbij horen.</li>
            <li><strong>Admin</strong> — geef of ontneem beheerdersrechten. Je eigen
                account kun je niet wijzigen.</li>
            <li><strong>Verwijderen</strong> — klik op Verwijder, daarna verschijnt
                een rood bevestigingsvak. Pas na "Ja, verwijder definitief" wordt de
                gebruiker (inclusief aankopen) verwijderd. Dit kan niet ongedaan gemaakt
                worden.</li>
        </ul>
    </section>

    <!-- ============================================================ -->
    <section style="margin-bottom:2.5rem;">
        <h2>5. Verdachte registraties (bots)</h2>
        <p>Bij elke nieuwe registratie krijgen de beheerders automatisch een
           e-mail. Lijkt een account op een bot (willekeurige naam/e-mail,
           wegwerp-adres), dan staat in het onderwerp
           <strong>&#9888; VERDACHTE registratie</strong>.</p>
        <p>Controleer zo'n account onder Gebruikers en verwijder het indien nodig.
           Echte klanten worden niet gemarkeerd.</p>
    </section>

    <!-- ============================================================ -->
    <section style="margin-bottom:1rem;">
        <h2>6. Veelgestelde vragen</h2>
        <p><strong>Een klant ziet zijn video niet.</strong><br>
           Controleer: is de video <em>actief</em>? Hoort hij bij een
           <em>event</em> waarvan de klant de code nog niet heeft ingevoerd?
           Heeft de klant daadwerkelijk <em>betaald</em> (zie Gebruikers &rarr; Details)?</p>

        <p><strong>De video speelt niet af.</strong><br>
           Controleer of de <em>bestandsnaam</em> in de site exact overeenkomt met
           het geüploade bestand (let op hoofdletters en <code>.mp4</code>).</p>

        <p><strong>Een klant kan niet betalen.</strong><br>
           Dit loopt via Mollie. Controleer in het Mollie-dashboard of er een
           betaling is gestart. Bij twijfel: vraag de eigenaar.</p>
    </section>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
