<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

$user = currentUser();

// Haal alle actieve video's op inclusief staffelinfo
$stmt = db()->query(
    'SELECT v.id, v.title, v.description, v.price, v.thumbnail, v.staffel_id
     FROM videos v
     WHERE v.active = 1
     ORDER BY v.created_at DESC'
);
$videos = $stmt->fetchAll();

// Haal alle aankopen van deze gebruiker op
$stmt = db()->prepare(
    "SELECT video_id, status FROM purchases WHERE user_id = ?"
);
$stmt->execute([$user['id']]);
$purchaseRows = $stmt->fetchAll();

// Maak een snel opzoekbaar array: video_id => status
$purchases = [];
foreach ($purchaseRows as $row) {
    $purchases[(int) $row['video_id']] = $row['status'];
}

// Tel betaalde aankopen per staffel voor staffelprijsberekening
$paidPerStaffel = [];
foreach ($videos as $v) {
    $vid = (int) $v['id'];
    $sid = (int) ($v['staffel_id'] ?? 0);
    if ($sid > 0 && ($purchases[$vid] ?? null) === 'paid') {
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

<?php if (empty($videos)): ?>
    <p class="text-muted">Er zijn nog geen video's beschikbaar.</p>
<?php else: ?>

<div class="video-grid">
    <?php foreach ($videos as $v):
        $vid      = (int) $v['id'];
        $sid      = (int) ($v['staffel_id'] ?? 0);
        $status   = $purchases[$vid] ?? null;
        $isPaid   = $status === 'paid';
        $isPending = in_array($status, ['open', 'pending'], true);

        // Bereken te betalen prijs
        if ($sid > 0 && !$isPaid) {
            $alGekocht  = $paidPerStaffel[$sid] ?? 0;
            $staffelPrijs = berekenStaffelprijs($sid, $alGekocht);
            $toonPrijs  = $staffelPrijs ?? (float) $v['price'];
        } else {
            $toonPrijs  = (float) $v['price'];
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
                <span class="video-card__price">&euro; <?= number_format($toonPrijs, 2, ',', '.') ?></span>

                <?php if ($isPaid): ?>
                    <a href="<?= BASE_URL ?>/members/watch.php?id=<?= $vid ?>" class="btn btn-success btn-sm">
                        &#9654; Bekijk
                    </a>
                <?php elseif ($isPending): ?>
                    <span class="badge-pending">Betaling in behandeling</span>
                <?php else: ?>
                    <form method="post" action="<?= BASE_URL ?>/payment/checkout.php">
                        <input type="hidden" name="video_id" value="<?= $vid ?>">
                        <input type="hidden" name="csrf_token" value="<?php
                            require_once __DIR__ . '/../includes/csrf.php';
                            echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8');
                        ?>">
                        <button type="submit" class="btn btn-primary btn-sm">
                            Koop toegang
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
