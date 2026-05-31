<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

requireAdmin();

$message = '';
$error   = '';

// ============================================================
// POST-acties
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $postAction = $_POST['post_action'] ?? '';
    $userId     = (int) ($_POST['user_id'] ?? 0);

    // Gebruiker verwijderen
    if ($postAction === 'delete_user' && $userId > 0) {
        $me = currentUser();
        if ($userId === (int) $me['id']) {
            $error = 'Je kunt je eigen account niet verwijderen.';
        } else {
            $stmt = db()->prepare('SELECT name, email FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$userId]);
            $target = $stmt->fetch();
            if ($target) {
                db()->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
                $message = 'Gebruiker "' . htmlspecialchars($target['name'], ENT_QUOTES, 'UTF-8')
                    . '" (' . htmlspecialchars($target['email'], ENT_QUOTES, 'UTF-8') . ') is verwijderd.';
                // Sluit detail-panel als die open stond voor deze user
                if (isset($_GET['user']) && (int) $_GET['user'] === $userId) {
                    header('Location: ' . BASE_URL . '/admin/users.php?deleted=1');
                    exit;
                }
            }
        }
    }

    // Admin-status in-/uitschakelen (bescherm eigen account)
    if ($postAction === 'toggle_admin' && $userId > 0) {
        $me = currentUser();
        if ($userId === (int) $me['id']) {
            $error = 'Je kunt je eigen admin-rechten niet wijzigen.';
        } else {
            $stmt = db()->prepare('SELECT is_admin FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$userId]);
            $row = $stmt->fetch();
            if ($row) {
                $new = $row['is_admin'] ? 0 : 1;
                db()->prepare('UPDATE users SET is_admin = ? WHERE id = ?')->execute([$new, $userId]);
                $message = $new ? 'Gebruiker heeft nu admin-rechten.' : 'Admin-rechten ingetrokken.';
            }
        }
    }
}

// ============================================================
// Data ophalen
// ============================================================

// Alle gebruikers + event-toegangen + aankopen als sub-query tellers
// "Online" = last_activity binnen de laatste 15 minuten
$users = db()->query(
    'SELECT u.id, u.email, u.name, u.is_admin, u.created_at, u.last_activity,
            COUNT(DISTINCT ea.event_id)   AS event_count,
            COUNT(DISTINCT p.id)          AS purchase_count,
            SUM(CASE WHEN p.status = \'paid\' THEN p.amount ELSE 0 END) AS total_paid
     FROM users u
     LEFT JOIN event_access ea ON ea.user_id = u.id
     LEFT JOIN purchases    p  ON p.user_id  = u.id
     GROUP BY u.id
     ORDER BY u.last_activity DESC, u.created_at DESC'
)->fetchAll();

// Detail-view: één gebruiker uitklappen
$detailUserId = isset($_GET['user']) ? (int) $_GET['user'] : 0;
$detail       = null;
$detailEvents = [];
$detailPurchases = [];

if ($detailUserId > 0) {
    $stmt = db()->prepare('SELECT id, email, name, is_admin, created_at FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$detailUserId]);
    $detail = $stmt->fetch();

    if ($detail) {
        // Events waartoe deze gebruiker toegang heeft
        $stmt = db()->prepare(
            'SELECT e.naam, e.organisator, ea.unlocked_at
             FROM event_access ea
             JOIN events e ON e.id = ea.event_id
             WHERE ea.user_id = ?
             ORDER BY ea.unlocked_at DESC'
        );
        $stmt->execute([$detailUserId]);
        $detailEvents = $stmt->fetchAll();

        // Aankopen
        $stmt = db()->prepare(
            'SELECT p.id, v.title AS video_title, p.amount, p.status, p.created_at, p.paid_at
             FROM purchases p
             JOIN videos v ON v.id = p.video_id
             WHERE p.user_id = ?
             ORDER BY p.created_at DESC'
        );
        $stmt->execute([$detailUserId]);
        $detailPurchases = $stmt->fetchAll();
    } else {
        $detailUserId = 0;
    }
}

// ============================================================
// View
// ============================================================
$pageTitle = 'Gebruikers — HB Foto & Video';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($message !== ''): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<!-- Navigatie tabs (zelfde patroon als admin/index.php) -->
<nav style="display:flex;gap:.75rem;margin-bottom:1.75rem;border-bottom:1px solid var(--border);padding-bottom:.75rem;flex-wrap:wrap;">
    <a href="<?= BASE_URL ?>/admin/"                class="btn btn-sm btn-secondary">&#9664; Video's</a>
    <a href="<?= BASE_URL ?>/admin/users.php"        class="btn btn-sm btn-primary">Gebruikers</a>
    <a href="<?= BASE_URL ?>/admin/staffels.php"     class="btn btn-sm btn-secondary">&#9654; Staffels</a>
    <a href="<?= BASE_URL ?>/admin/events.php"       class="btn btn-sm btn-secondary">&#9654; Events</a>
</nav>

<div class="page-header">
    <h1>Gebruikers <span style="font-size:1rem;font-weight:400;color:var(--text-muted)">(<?= count($users) ?>)</span></h1>
</div>

<!-- ============================================================
     Gebruikersoverzicht
     ============================================================ -->
<div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Naam</th>
                <th>E-mail</th>
                <th>Geregistreerd</th>
                <th title="Actief binnen de laatste 15 minuten">Online</th>
                <th>Events</th>
                <th>Aankopen</th>
                <th>Betaald</th>
                <th>Rol</th>
                <th>Acties</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <?php $isDetail = ($detailUserId === (int) $u['id']); ?>
            <tr <?= $isDetail ? 'style="background:var(--surface-hover, rgba(255,255,255,.05))"' : '' ?>>
                <td><?= (int) $u['id'] ?></td>
                <td>
                    <a href="?user=<?= (int) $u['id'] ?><?= $isDetail ? '' : '' ?>"
                       style="color:inherit;text-decoration:none;font-weight:500;">
                        <?= htmlspecialchars($u['name'], ENT_QUOTES, 'UTF-8') ?>
                    </a>
                </td>
                <td style="font-size:.88rem"><?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') ?></td>
                <td style="font-size:.85rem;white-space:nowrap">
                    <?= date('d-m-Y', strtotime($u['created_at'])) ?>
                </td>
                <td style="text-align:center">
                    <?php
                    $isOnline = $u['last_activity']
                        && (time() - strtotime($u['last_activity'])) <= 900;
                    ?>
                    <?php if ($isOnline): ?>
                        <span class="status-paid" title="Actief: <?= date('H:i', strtotime($u['last_activity'])) ?>">&#9679; online</span>
                    <?php elseif ($u['last_activity']): ?>
                        <span class="text-muted" style="font-size:.8rem" title="Laatst actief">
                            <?php
                            $diff = time() - strtotime($u['last_activity']);
                            if ($diff < 3600)       echo round($diff / 60) . ' min geleden';
                            elseif ($diff < 86400)  echo round($diff / 3600) . ' u geleden';
                            else                    echo date('d-m-Y', strtotime($u['last_activity']));
                            ?>
                        </span>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td style="text-align:center">
                    <?php if ($u['event_count'] > 0): ?>
                        <span class="status-paid"><?= (int) $u['event_count'] ?></span>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td style="text-align:center">
                    <?php if ($u['purchase_count'] > 0): ?>
                        <?= (int) $u['purchase_count'] ?>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td style="text-align:right;white-space:nowrap">
                    <?php if ((float) $u['total_paid'] > 0): ?>
                        &euro;&nbsp;<?= number_format((float) $u['total_paid'], 2, ',', '.') ?>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($u['is_admin']): ?>
                        <span class="status-paid">Admin</span>
                    <?php else: ?>
                        <span class="text-muted" style="font-size:.85rem">Gebruiker</span>
                    <?php endif; ?>
                </td>
                <td class="actions" style="white-space:nowrap">
                    <a href="?user=<?= (int) $u['id'] ?>"
                       class="btn btn-sm btn-secondary">
                        <?= $isDetail ? 'Sluiten' : 'Details' ?>
                    </a>
                    <?php $me = currentUser(); if ((int) $u['id'] !== (int) $me['id']): ?>
                    <form method="post" style="display:inline" onsubmit="return confirm('Bevestigen?')">
                        <?= csrfField() ?>
                        <input type="hidden" name="post_action" value="toggle_admin">
                        <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-secondary"
                                title="<?= $u['is_admin'] ? 'Admin-rechten intrekken' : 'Admin-rechten geven' ?>">
                            <?= $u['is_admin'] ? '&#x2193; Admin' : '&#x2191; Admin' ?>
                        </button>
                    </form>
                    <form method="post" style="display:inline"
                          onsubmit="return confirmDelete(<?= (int) $u['id'] ?>, <?= json_encode($u['name']) ?>)">
                        <?= csrfField() ?>
                        <input type="hidden" name="post_action" value="delete_user">
                        <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                        <button type="submit" class="btn btn-sm"
                                style="background:#c0392b;color:#fff;border-color:#c0392b"
                                title="Gebruiker verwijderen">&#x2715; Verwijder</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>

            <?php if ($isDetail && $detail): ?>
            <tr>
                <td colspan="9" style="padding:0;">
                    <div style="padding:1.25rem 1.5rem;border-top:1px solid var(--border);border-bottom:1px solid var(--border);background:var(--surface);">

                        <h3 style="margin:0 0 1rem">
                            <?= htmlspecialchars($detail['name'], ENT_QUOTES, 'UTF-8') ?>
                            <span style="font-weight:400;font-size:.9rem;color:var(--text-muted)">
                                — <?= htmlspecialchars($detail['email'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </h3>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">

                            <!-- Events -->
                            <div>
                                <h4 style="margin:0 0 .6rem;font-size:.9rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted)">
                                    Event-toegang (<?= count($detailEvents) ?>)
                                </h4>
                                <?php if (empty($detailEvents)): ?>
                                    <p class="text-muted" style="font-size:.88rem">Geen events ontgrendeld.</p>
                                <?php else: ?>
                                    <table style="width:100%;font-size:.88rem;border-collapse:collapse;">
                                        <thead>
                                            <tr style="border-bottom:1px solid var(--border);">
                                                <th style="text-align:left;padding:.25rem .5rem">Event</th>
                                                <th style="text-align:left;padding:.25rem .5rem">Organisator</th>
                                                <th style="text-align:left;padding:.25rem .5rem">Ontgrendeld</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($detailEvents as $ev): ?>
                                            <tr>
                                                <td style="padding:.3rem .5rem"><?= htmlspecialchars($ev['naam'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td style="padding:.3rem .5rem;color:var(--text-muted)"><?= htmlspecialchars($ev['organisator'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td style="padding:.3rem .5rem;white-space:nowrap">
                                                    <?= date('d-m-Y H:i', strtotime($ev['unlocked_at'])) ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>

                            <!-- Aankopen -->
                            <div>
                                <h4 style="margin:0 0 .6rem;font-size:.9rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted)">
                                    Aankopen (<?= count($detailPurchases) ?>)
                                </h4>
                                <?php if (empty($detailPurchases)): ?>
                                    <p class="text-muted" style="font-size:.88rem">Nog geen aankopen.</p>
                                <?php else: ?>
                                    <table style="width:100%;font-size:.88rem;border-collapse:collapse;">
                                        <thead>
                                            <tr style="border-bottom:1px solid var(--border);">
                                                <th style="text-align:left;padding:.25rem .5rem">Video</th>
                                                <th style="text-align:right;padding:.25rem .5rem">Bedrag</th>
                                                <th style="text-align:left;padding:.25rem .5rem">Status</th>
                                                <th style="text-align:left;padding:.25rem .5rem">Datum</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($detailPurchases as $p): ?>
                                            <tr>
                                                <td style="padding:.3rem .5rem"><?= htmlspecialchars($p['video_title'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td style="padding:.3rem .5rem;text-align:right;white-space:nowrap">
                                                    &euro;&nbsp;<?= number_format((float) $p['amount'], 2, ',', '.') ?>
                                                </td>
                                                <td style="padding:.3rem .5rem">
                                                    <?php
                                                    $statusClass = match($p['status']) {
                                                        'paid'    => 'status-paid',
                                                        'open','pending' => 'status-pending',
                                                        default   => 'status-inactive',
                                                    };
                                                    ?>
                                                    <span class="<?= $statusClass ?>"><?= htmlspecialchars($p['status'], ENT_QUOTES, 'UTF-8') ?></span>
                                                </td>
                                                <td style="padding:.3rem .5rem;font-size:.82rem;white-space:nowrap">
                                                    <?= date('d-m-Y', strtotime($p['created_at'])) ?>
                                                    <?php if ($p['paid_at']): ?>
                                                        <br><span style="color:var(--text-muted)">betaald: <?= date('d-m-Y H:i', strtotime($p['paid_at'])) ?></span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>

                        </div><!-- /grid -->
                    </div>
                </td>
            </tr>
            <?php endif; ?>

        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
function confirmDelete(userId, userName) {
    // Eerste check
    if (!confirm('Gebruiker "' + userName + '" verwijderen?\n\nAlle aankopen en event-toegangen worden ook verwijderd.')) {
        return false;
    }
    // Tweede check
    var typed = prompt('Typ de naam van de gebruiker om te bevestigen:');
    if (typed === null) return false;
    if (typed.trim() !== userName) {
        alert('Naam komt niet overeen. Verwijdering geannuleerd.');
        return false;
    }
    return true;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
