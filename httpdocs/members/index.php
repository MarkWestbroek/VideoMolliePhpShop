<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/events.php';

requireLogin();

$user = currentUser();

// IP-tracking: controleer op blokkade door te veel login-IP's
$ipStatus = getViewingBlockedStatus();

if ($ipStatus['blocked']) {
    $pageTitle = 'Toegang geblokkeerd — HB Foto & Video';
    require_once __DIR__ . '/../includes/header.php';
    ?>
    <div class="form-card" style="text-align:center;">
        <h1 style="color:#e74c3c;">&#9888; Toegang geblokkeerd</h1>
        <p style="font-size:1.05rem;margin:1.5rem 0;line-height:1.6;">
            Je hebt ingelogd vanaf te veel verschillende apparaten of locaties
            (je account is al gekoppeld aan <?= $ipStatus['ipCount'] ?> IP-adres(sen); maximaal <?= $ipStatus['maxIps'] ?>).
        </p>
        <p style="margin-bottom:1.5rem;color:var(--text-muted);">
            Om misbruik te voorkomen is het bekijken van video's tijdelijk niet mogelijk.<br>
            Neem contact op met de beheerder om dit op te lossen.
        </p>
        <a href="<?= BASE_URL ?>/members/contact.php" class="btn btn-primary">
            &#9993; Contact opnemen
        </a>
    </div>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Bepaal tot welke events deze gebruiker toegang heeft
$eventIds = getUserEventIds((int) $user['id']);

// Haal video's op: openbaar (event_id IS NULL) of een event waartoe de gebruiker toegang heeft
if (empty($eventIds)) {
    $stmt = db()->query(
        'SELECT v.id, v.title, v.description, v.price, v.thumbnail, v.staffel_id, v.event_id
         FROM videos v
         WHERE v.active = 1 AND v.is_test = 0 AND v.event_id IS NULL
         ORDER BY v.created_at DESC'
    );
} else {
    $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
    $stmt = db()->prepare(
        "SELECT v.id, v.title, v.description, v.price, v.thumbnail, v.staffel_id, v.event_id
         FROM videos v
         WHERE v.active = 1 AND v.is_test = 0 AND (v.event_id IS NULL OR v.event_id IN ($placeholders))
         ORDER BY v.created_at DESC"
    );
    $stmt->execute($eventIds);
}
$videos = $stmt->fetchAll();

// Haal alle aankopen van deze gebruiker op
$stmt = db()->prepare(
    "SELECT video_id, status, amount FROM purchases WHERE user_id = ?"
);
$stmt->execute([$user['id']]);
$purchaseRows = $stmt->fetchAll();

// Maak een snel opzoekbaar array: video_id => ['status' => ..., 'amount' => ...]
$purchases = [];
foreach ($purchaseRows as $row) {
    $purchases[(int) $row['video_id']] = [
        'status' => $row['status'],
        'amount' => (float) $row['amount'],
    ];
}

// Tel betaalde aankopen per staffel voor staffelprijsberekening
$paidPerStaffel = [];
foreach ($videos as $v) {
    $vid = (int) $v['id'];
    $sid = (int) ($v['staffel_id'] ?? 0);
    if ($sid > 0 && ($purchases[$vid]['status'] ?? null) === 'paid') {
        $paidPerStaffel[$sid] = ($paidPerStaffel[$sid] ?? 0) + 1;
    }
}

/**
 * Bereken prijs voor een video op basis van staffeltrappen.
 * Geeft de prijs van de VOLGENDE aankoop (= huidige aantal + 1).
 */
function berekenStaffelprijs(int $staffelId, int $alGekocht): ?float
{
    $volgend = $alGekocht + 1;
    $stmt = db()->prepare(
        'SELECT prijs FROM staffelprijzen
         WHERE staffel_id = ? AND aantal_van <= ? AND aantal_tot >= ?
         ORDER BY aantal_van DESC LIMIT 1'
    );
    $stmt->execute([$staffelId, $volgend, $volgend]);
    $row = $stmt->fetch();
    return $row ? (float) $row['prijs'] : null;
}

$pageTitle = "Mijn video's — HB Foto & Video";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Video's</h1>
</div>

<p class="text-muted" style="margin-bottom:1.5rem;font-size:.9rem;">
    Heb je een toegangscode van een event ontvangen?
    <a href="<?= BASE_URL ?>/members/account.php">Voer deze in op je account</a>
    om de bijbehorende video's te zien.
</p>

<?php if (empty($videos)): ?>
    <p class="text-muted">Er zijn nog geen video's voor je beschikbaar. Heb je een event-toegangscode?
        <a href="<?= BASE_URL ?>/members/account.php">Voer deze in</a>.</p>
<?php else: ?>

<div class="video-grid">
    <?php foreach ($videos as $v):
        $vid      = (int) $v['id'];
        $sid      = (int) ($v['staffel_id'] ?? 0);
        $status   = $purchases[$vid]['status'] ?? null;
        $isPaid    = $status === 'paid';
        $isPending = in_array($status, ['open', 'pending'], true);
        $isGratis  = !$isPaid && !$isPending && (float)$v['price'] === 0.0 && empty($v['staffel_id']);

        // Bereken te betalen / betaalde prijs
        if ($isPaid) {
            // Toon werkelijk betaald bedrag
            $toonPrijs = $purchases[$vid]['amount'];
        } elseif ($sid > 0) {
            $alGekocht    = $paidPerStaffel[$sid] ?? 0;
            $staffelPrijs = berekenStaffelprijs($sid, $alGekocht);
            $toonPrijs    = $staffelPrijs ?? (float) $v['price'];
        } else {
            $toonPrijs = (float) $v['price'];
        }
    ?>
    <div class="video-card">
        <div class="video-card__thumb">
            <?php if ($v['thumbnail']): ?>
                <img src="<?= BASE_URL ?>/assets/thumbs/<?= htmlspecialchars($v['thumbnail'], ENT_QUOTES, 'UTF-8') ?>"
                     alt="<?= htmlspecialchars($v['title'], ENT_QUOTES, 'UTF-8') ?>">
            <?php else: ?>
                <div class="video-card__thumb-placeholder">&#9654;</div>
            <?php endif; ?>
        </div>

        <div class="video-card__body">
            <h3 class="video-card__title"><?= htmlspecialchars($v['title'], ENT_QUOTES, 'UTF-8') ?></h3>

            <?php if ($v['description']): ?>
                <p class="video-card__desc">
                    <?= htmlspecialchars(mb_strimwidth($v['description'], 0, 140, '…'), ENT_QUOTES, 'UTF-8') ?>
                </p>
            <?php endif; ?>

            <div class="video-card__footer">
                <?php if ($isGratis): ?>
                    <span class="badge-free tt" data-tooltip="Gratis te bekijken — geen betaling nodig.">Gratis</span>
                    <form method="post" action="<?= BASE_URL ?>/payment/checkout.php">
                        <input type="hidden" name="video_id" value="<?= $vid ?>">
                        <input type="hidden" name="csrf_token" value="<?php
                            require_once __DIR__ . '/../includes/csrf.php';
                            echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8');
                        ?>">
                        <button type="submit" class="btn btn-success btn-sm tt"
                                data-tooltip="Geen betaling nodig — klik om direct te bekijken.">
                            &#9654; Kijk gratis
                        </button>
                    </form>
                <?php else: ?>
                    <span class="video-card__price tt"
                        data-tooltip="<?php
                            if ($isPaid)       echo 'Jouw aankoopbedrag';
                            elseif ($sid > 0)  echo "Staffelkorting: hoe meer video's uit deze serie je koopt, hoe lager de prijs.";
                            else               echo 'Eenmalige prijs voor permanente toegang tot deze video.';
                        ?>">
                        &euro; <?= number_format($toonPrijs, 2, ',', '.') ?>
                        <?php if ($sid > 0 && !$isPaid): ?>
                            <span style="font-size:.8rem;opacity:.65;">&#9432;</span>
                        <?php endif; ?>
                    </span>

                    <?php if ($isPaid): ?>
                        <a href="<?= BASE_URL ?>/members/watch.php?id=<?= $vid ?>" class="btn btn-success btn-sm tt"
                           data-tooltip="Je hebt deze video gekocht — klik om te bekijken.">
                            &#9654; Bekijk
                        </a>
                    <?php elseif ($isPending): ?>
                        <span class="badge-pending tt"
                              data-tooltip="Je betaling wordt verwerkt. Dit duurt meestal minder dan een minuut. Ververs de pagina om de status te zien.">
                            Betaling in behandeling
                        </span>
                    <?php else: ?>
                        <form method="post" action="<?= BASE_URL ?>/payment/checkout.php">
                            <input type="hidden" name="video_id" value="<?= $vid ?>">
                            <input type="hidden" name="csrf_token" value="<?php
                                require_once __DIR__ . '/../includes/csrf.php';
                                echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8');
                            ?>">
                            <button type="submit" class="btn btn-primary btn-sm tt"
                                    data-tooltip="Veilig betalen via Mollie (iDEAL, creditcard e.d.). Na betaling kun je direct bekijken.">
                                Koop toegang
                            </button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
