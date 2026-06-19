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

    // Gebruiker definitief verwijderen (na bevestigingsstap)
    if ($postAction === 'delete_user_confirmed' && $userId > 0) {
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
                header('Location: ' . BASE_URL . '/admin/users.php?deleted=1');
                exit;
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

    // IP-records resetten voor gebruiker
    if ($postAction === 'reset_ips' && $userId > 0) {
        resetLoginIps($userId);
        $message = 'Alle login-IP\'s zijn verwijderd. De gebruiker kan opnieuw inloggen vanaf een nieuw IP.';
        header('Location: ' . BASE_URL . '/admin/users.php?ips_reset=1');
        exit;
    }

    // ISP-info opzoeken voor IP's die nog geen provider hebben (max 20 per batch)
    if ($postAction === 'lookup_missing_ips') {
        $stmt = db()->query('SELECT id, ip_address FROM login_ips WHERE isp IS NULL LIMIT 20');
        $ips  = $stmt->fetchAll();
        $done = 0;
        foreach ($ips as $row) {
            $info = lookupIpInfo($row['ip_address']);
            if ($info) {
                db()->prepare('UPDATE login_ips SET isp = ?, is_mobile = ? WHERE id = ?')
                    ->execute([$info['isp'], $info['is_mobile'], $row['id']]);
                $done++;
            }
        }
        $remaining = (int) db()->query('SELECT COUNT(*) FROM login_ips WHERE isp IS NULL')->fetchColumn();
        $message = "ISP-info opgezocht: {$done} van " . count($ips) . " IP's bijgewerkt. Nog {$remaining} IP's zonder provider.";
    }
}

// ============================================================
// Data ophalen
// ============================================================

// Alle gebruikers + event-toegangen + aankopen als sub-query tellers
// "Online" = last_activity binnen de laatste 15 minuten
$search      = trim($_GET['search'] ?? '');
$sort        = $_GET['sort'] ?? 'last_activity';
$dir         = strtoupper($_GET['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
$roleFilter   = $_GET['role']   ?? '';
$emailFilter  = $_GET['everify'] ?? '';
$onlineFilter = $_GET['online']  ?? '';
$ipFilter     = $_GET['ips']     ?? '';
$purchFilter  = $_GET['purchases'] ?? '';

$allowedSorts = [
    'id'             => 'u.id',
    'last_activity'  => 'u.last_activity',
    'created_at'     => 'u.created_at',
    'name'           => 'u.name',
    'email'          => 'u.email',
    'ip_count'       => 'ip_count',
    'purchase_count' => 'purchase_count',
    'total_paid'     => 'total_paid',
];
$sortCol = $allowedSorts[$sort] ?? 'u.last_activity';

$conditions = [];
$params     = [];

if ($search !== '') {
    $conditions[] = '(u.name LIKE ? OR u.email LIKE ?)';
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
}
if ($roleFilter === 'admin') {
    $conditions[] = 'u.is_admin = 1';
} elseif ($roleFilter === 'user') {
    $conditions[] = 'u.is_admin = 0';
}
if ($emailFilter === 'verified') {
    $conditions[] = 'u.email_verified_at IS NOT NULL';
} elseif ($emailFilter === 'unverified') {
    $conditions[] = 'u.email_verified_at IS NULL';
}
if ($onlineFilter === 'streaming') {
    $conditions[] = 'u.streaming_at >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)';
} elseif ($onlineFilter === 'online') {
    $conditions[] = 'u.last_activity >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)';
} elseif ($onlineFilter === 'offline') {
    $conditions[] = '(u.last_activity IS NULL OR u.last_activity < DATE_SUB(NOW(), INTERVAL 15 MINUTE))';
}

$where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';

// HAVING-condities voor geaggregeerde kolommen
$havingConds = [];
if ($ipFilter === 'none') {
    $havingConds[] = 'ip_count = 0';
} elseif ($ipFilter === '1-2') {
    $havingConds[] = 'ip_count BETWEEN 1 AND 2';
} elseif ($ipFilter === '3plus') {
    $havingConds[] = 'ip_count >= 3';
}
if ($purchFilter === 'none') {
    $havingConds[] = 'purchase_count = 0';
} elseif ($purchFilter === '1-5') {
    $havingConds[] = 'purchase_count BETWEEN 1 AND 5';
} elseif ($purchFilter === '6plus') {
    $havingConds[] = 'purchase_count >= 6';
}
$having = $havingConds ? ' HAVING ' . implode(' AND ', $havingConds) : '';

$users = db()->prepare(
    "SELECT u.id, u.email, u.name, u.is_admin, u.email_verified_at, u.created_at, u.last_activity, u.streaming_at,
            COUNT(DISTINCT ea.event_id)   AS event_count,
            COUNT(DISTINCT p.id)          AS purchase_count,
            SUM(CASE WHEN p.status = 'paid' THEN p.amount ELSE 0 END) AS total_paid,
            COALESCE((SELECT COUNT(*) FROM login_ips li WHERE li.user_id = u.id AND li.last_seen >= DATE_SUB(NOW(), INTERVAL 14 DAY)), 0) AS ip_count
     FROM users u
     LEFT JOIN event_access ea ON ea.user_id = u.id
     LEFT JOIN purchases    p  ON p.user_id  = u.id
     {$where}
     GROUP BY u.id
     {$having}
     ORDER BY {$sortCol} {$dir}"
);
$users->execute($params);
$users = $users->fetchAll();

// Verwijder-bevestiging: welke user staat in 'vraag-bevestiging'-modus?
$confirmDeleteId = isset($_GET['delete']) ? (int) $_GET['delete'] : 0;

// Detail-view: één gebruiker uitklappen
$detailUserId = isset($_GET['user']) ? (int) $_GET['user'] : 0;
$detail       = null;
$detailEvents   = [];
$detailPurchases = [];
$detailIps       = [];
$detailViews     = [];

if ($detailUserId > 0) {
    $stmt = db()->prepare('SELECT id, email, name, is_admin, email_verified_at, created_at FROM users WHERE id = ? LIMIT 1');
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

        // Login IP's
        $detailIps = getUserLoginIps($detailUserId);

        // Video-weergaven
        $stmt = db()->prepare(
            'SELECT v.title AS video_title, vv.watched_at
             FROM video_views vv
             JOIN videos v ON v.id = vv.video_id
             WHERE vv.user_id = ?
             ORDER BY vv.watched_at DESC
             LIMIT 50'
        );
        $stmt->execute([$detailUserId]);
        $detailViews = $stmt->fetchAll();
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
    <a href="<?= BASE_URL ?>/admin/help.php"         class="btn btn-sm btn-secondary">&#9432; Handleiding</a>
</nav>

<div class="page-header">
    <h1>Gebruikers <span style="font-size:1rem;font-weight:400;color:var(--text-muted)">(<?= count($users) ?>)</span></h1>
    <?php
    $missingIsp = (int) db()->query('SELECT COUNT(*) FROM login_ips WHERE isp IS NULL')->fetchColumn();
    if ($missingIsp > 0): ?>
        <form method="post" style="display:inline;margin-left:1rem;">
            <?= csrfField() ?>
            <input type="hidden" name="post_action" value="lookup_missing_ips">
            <button type="submit" class="btn btn-sm btn-secondary" title="Zoek ISP-info op voor IP's zonder provider (max 20 per klik)">
                &#128269; Zoek ISP op (nog <?= $missingIsp ?>)
            </button>
        </form>
    <?php endif; ?>
</div>

<form id="search-form" style="margin-bottom:1rem;display:flex;gap:.5rem;flex-wrap:wrap;" onsubmit="var p=new URLSearchParams(window.location.search);var s=this.search.value.trim();if(s===''){p.delete('search');}else{p.set('search',s);}window.location.search=p.toString();return false;">
    <input type="text" id="search-input" name="search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="Zoek op naam of e-mail..." style="flex:1;min-width:200px;padding:.5rem;border:1px solid var(--border);border-radius:4px;font-size:.9rem;background:var(--surface);color:#eee;">
    <button type="submit" class="btn btn-sm btn-primary">Zoeken</button>
    <?php if ($search !== '' || $roleFilter !== '' || $emailFilter !== '' || $onlineFilter !== '' || $ipFilter !== '' || $purchFilter !== ''): ?>
        <a href="?" class="btn btn-sm btn-secondary">Wis filters</a>
    <?php endif; ?>
</form>

<!-- ============================================================
     Gebruikersoverzicht
     ============================================================ -->
<?php
// Helper voor sorteerlinks
$sortLink = function(string $col, string $label) use ($sort, $dir, $search, $roleFilter, $emailFilter, $onlineFilter, $ipFilter, $purchFilter): string {
    $newDir = ($sort === $col && $dir === 'ASC') ? 'DESC' : 'ASC';
    $arrow  = ($sort === $col) ? ($dir === 'ASC' ? ' &#9650;' : ' &#9660;') : '';
    $q      = '?sort=' . $col . '&dir=' . $newDir
            . ($search !== '' ? '&search=' . urlencode($search) : '')
            . ($roleFilter !== '' ? '&role=' . urlencode($roleFilter) : '')
            . ($emailFilter !== '' ? '&everify=' . urlencode($emailFilter) : '')
            . ($onlineFilter !== '' ? '&online=' . urlencode($onlineFilter) : '')
            . ($ipFilter !== '' ? '&ips=' . urlencode($ipFilter) : '')
            . ($purchFilter !== '' ? '&purchases=' . urlencode($purchFilter) : '');
    return '<a href="' . htmlspecialchars($q) . '" style="color:inherit;text-decoration:none;">' . $label . $arrow . '</a>';
};
?>
<div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr>
                <th><?= $sortLink('id', 'ID') ?></th>
                <th><?= $sortLink('name', 'Naam') ?></th>
                <th><?= $sortLink('email', 'E-mail') ?></th>
                <th><?= $sortLink('created_at', 'Geregistreerd') ?></th>
                <th title="Actief binnen de laatste 15 minuten">Online</th>
                <th><?= $sortLink('ip_count', "IP's") ?></th>
                <th>E-mail</th>
                <th>Events</th>
                <th><?= $sortLink('purchase_count', 'Aankopen') ?></th>
                <th><?= $sortLink('total_paid', 'Betaald') ?></th>
                <th>Rol</th>
                <th>Acties</th>
            </tr>
            <tr style="background:var(--surface-hover, rgba(255,255,255,.02));">
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td>
                    <select class="filter-drop" data-param="online" style="width:100%;padding:.2rem;font-size:.75rem;border:1px solid var(--border);border-radius:3px;background:#2a2a2a;color:#ddd;">
                        <option value="" <?= $onlineFilter === '' ? 'selected' : '' ?>>Alle</option>
                        <option value="streaming" <?= $onlineFilter === 'streaming' ? 'selected' : '' ?>>Stream</option>
                        <option value="online" <?= $onlineFilter === 'online' ? 'selected' : '' ?>>Online</option>
                        <option value="offline" <?= $onlineFilter === 'offline' ? 'selected' : '' ?>>Offline</option>
                    </select>
                </td>
                <td>
                    <select class="filter-drop" data-param="ips" style="width:100%;padding:.2rem;font-size:.75rem;border:1px solid var(--border);border-radius:3px;background:#2a2a2a;color:#ddd;">
                        <option value="" <?= $ipFilter === '' ? 'selected' : '' ?>>Alle</option>
                        <option value="none" <?= $ipFilter === 'none' ? 'selected' : '' ?>>0</option>
                        <option value="1-2" <?= $ipFilter === '1-2' ? 'selected' : '' ?>>1-2</option>
                        <option value="3plus" <?= $ipFilter === '3plus' ? 'selected' : '' ?>>3+</option>
                    </select>
                </td>
                <td>
                    <select class="filter-drop" data-param="everify" style="width:100%;padding:.2rem;font-size:.75rem;border:1px solid var(--border);border-radius:3px;background:#2a2a2a;color:#ddd;">
                        <option value="" <?= $emailFilter === '' ? 'selected' : '' ?>>Alle</option>
                        <option value="verified" <?= $emailFilter === 'verified' ? 'selected' : '' ?>>Geverifieerd</option>
                        <option value="unverified" <?= $emailFilter === 'unverified' ? 'selected' : '' ?>>Niet</option>
                    </select>
                </td>
                <td></td>
                <td>
                    <select class="filter-drop" data-param="purchases" style="width:100%;padding:.2rem;font-size:.75rem;border:1px solid var(--border);border-radius:3px;background:#2a2a2a;color:#ddd;">
                        <option value="" <?= $purchFilter === '' ? 'selected' : '' ?>>Alle</option>
                        <option value="none" <?= $purchFilter === 'none' ? 'selected' : '' ?>>0</option>
                        <option value="1-5" <?= $purchFilter === '1-5' ? 'selected' : '' ?>>1-5</option>
                        <option value="6plus" <?= $purchFilter === '6plus' ? 'selected' : '' ?>>6+</option>
                    </select>
                </td>
                <td></td>
                <td>
                    <select class="filter-drop" data-param="role" style="width:100%;padding:.2rem;font-size:.75rem;border:1px solid var(--border);border-radius:3px;background:#2a2a2a;color:#ddd;">
                        <option value="" <?= $roleFilter === '' ? 'selected' : '' ?>>Alle</option>
                        <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="user" <?= $roleFilter === 'user' ? 'selected' : '' ?>>Gebruiker</option>
                    </select>
                </td>
                <td></td>
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
                    $lastActivity = $u['last_activity'] ? time() - strtotime($u['last_activity']) : null;
                    $streamingAgo = $u['streaming_at'] ? time() - strtotime($u['streaming_at']) : null;
                    $isStreaming  = $streamingAgo !== null && $streamingAgo <= 120;  // 2 min = echte video-stream
                    $isOnline     = $lastActivity !== null && $lastActivity <= 900;   // 15 min = online
                    ?>
                    <?php if ($isStreaming): ?>
                        <span style="color:#e74c3c;font-weight:600;" title="Video aan het streamen (streaming_at: <?= date('H:i:s', strtotime($u['streaming_at'])) ?>)">&#9679; stream</span>
                    <?php elseif ($isOnline): ?>
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
                    <?php if ((int) $u['ip_count'] > 0): ?>
                        <?php if ((int) $u['ip_count'] >= 3): ?>
                            <span style="color:#e74c3c;font-weight:600;" title="Limiet bereikt — mogelijk geblokkeerd"><?= (int) $u['ip_count'] ?></span>
                        <?php else: ?>
                            <?= (int) $u['ip_count'] ?>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td style="text-align:center">
                    <?php if ($u['email_verified_at']): ?>
                        <span class="status-paid" title="Geverifieerd op <?= date('d-m-Y H:i', strtotime($u['email_verified_at'])) ?>">&#10003;</span>
                    <?php else: ?>
                        <span class="text-muted" style="color:#e67e22">&#9888; niet</span>
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
                    </form>                    <?php if ((int) $u['ip_count'] > 0): ?>
                    <form method="post" style="display:inline" onsubmit="return confirm('Alle login-IP\'s voor deze gebruiker verwijderen?')">
                        <?= csrfField() ?>
                        <input type="hidden" name="post_action" value="reset_ips">
                        <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-secondary"
                                title="IP-blokkade opheffen">
                            &#x21bb; IP's
                        </button>
                    </form>
                    <?php endif; ?>                    <?php if ($confirmDeleteId === (int) $u['id']): ?>
                        <!-- Bevestigingsknop zichtbaar als ?delete=ID in URL -->
                        <a href="?" class="btn btn-sm btn-secondary" title="Annuleren">Annuleer</a>
                    <?php else: ?>
                        <a href="?delete=<?= (int) $u['id'] ?>"
                           class="btn btn-sm"
                           style="background:#c0392b;color:#fff;border-color:#c0392b"
                           title="Gebruiker verwijderen">&#x2715; Verwijder</a>
                    <?php endif; ?>
                    <?php endif; ?>
                </td>
            </tr>

            <?php if ($confirmDeleteId === (int) $u['id']): ?>
            <tr>
                <td colspan="12" style="padding:0;">
                    <div style="padding:1.25rem 1.5rem;background:#3d0f0f;border-top:2px solid #c0392b;">
                        <strong style="color:#e74c3c;">&#9888; Gebruiker definitief verwijderen?</strong>
                        <p style="margin:.5rem 0 1rem;font-size:.92rem;">
                            Je staat op het punt om <strong><?= htmlspecialchars($u['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                            (<?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') ?>) te verwijderen.<br>
                            Alle aankopen en event-toegangen worden ook verwijderd. Dit is <strong>onomkeerbaar</strong>.
                        </p>
                        <form method="post" style="display:inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="post_action" value="delete_user_confirmed">
                            <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                            <button type="submit" class="btn"
                                    style="background:#c0392b;color:#fff;border-color:#c0392b;margin-right:.5rem">
                                Ja, verwijder definitief
                            </button>
                        </form>
                        <a href="?" class="btn btn-secondary">Annuleren</a>
                    </div>
                </td>
            </tr>
            <?php endif; ?>

            <?php if ($isDetail && $detail): ?>
            <tr>
                <td colspan="12" style="padding:0;">
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

                            <!-- Login IP's -->
                            <div>
                                <h4 style="margin:0 0 .6rem;font-size:.9rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted)">
                                    Login-IP's (<?= count($detailIps) ?>)
                                    <?php if (!empty($detailIps)): ?>
                                        <form method="post" style="display:inline;margin-left:.5rem;" onsubmit="return confirm('Alle login-IP's voor deze gebruiker verwijderen?')">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="post_action" value="reset_ips">
                                            <input type="hidden" name="user_id" value="<?= (int) $detail['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-secondary" style="font-size:.75rem;">&#x21bb; Reset</button>
                                        </form>
                                    <?php endif; ?>
                                </h4>
                                <?php if (empty($detailIps)): ?>
                                    <p class="text-muted" style="font-size:.88rem">Nog geen login-IP's geregistreerd.</p>
                                <?php else: ?>
                                    <table style="width:100%;font-size:.88rem;border-collapse:collapse;">
                                        <thead>
                                            <tr style="border-bottom:1px solid var(--border);">
                                                <th style="text-align:left;padding:.25rem .5rem">IP-adres</th>
                                                <th style="text-align:left;padding:.25rem .5rem">Provider</th>
                                                <th style="text-align:left;padding:.25rem .5rem">Eerst gezien</th>
                                                <th style="text-align:left;padding:.25rem .5rem">Laatst gezien</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($detailIps as $lip): ?>
                                            <tr>
                                                <td style="padding:.3rem .5rem;font-family:monospace;">
                                                    <?= htmlspecialchars($lip['ip_address'], ENT_QUOTES, 'UTF-8') ?>
                                                </td>
                                                <td style="padding:.3rem .5rem;font-size:.82rem;">
                                                    <?php if ($lip['isp']): ?>
                                                        <?= htmlspecialchars($lip['isp'], ENT_QUOTES, 'UTF-8') ?>
                                                        <?php if (($lip['is_mobile'] ?? 0)): ?>
                                                            <span style="color:var(--text-muted);">/ Mobiel</span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="padding:.3rem .5rem;font-size:.82rem;white-space:nowrap">
                                                    <?= date('d-m-Y H:i', strtotime($lip['first_seen'])) ?>
                                                </td>
                                                <td style="padding:.3rem .5rem;font-size:.82rem;white-space:nowrap">
                                                    <?= date('d-m-Y H:i', strtotime($lip['last_seen'])) ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>

                            <!-- Video-weergaven -->
                            <div>
                                <h4 style="margin:0 0 .6rem;font-size:.9rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted)">
                                    Bekeken video's (<?= count($detailViews) ?>)
                                </h4>
                                <?php if (empty($detailViews)): ?>
                                    <p class="text-muted" style="font-size:.88rem">Nog geen video's bekeken.</p>
                                <?php else: ?>
                                    <table style="width:100%;font-size:.88rem;border-collapse:collapse;">
                                        <thead>
                                            <tr style="border-bottom:1px solid var(--border);">
                                                <th style="text-align:left;padding:.25rem .5rem">Video</th>
                                                <th style="text-align:left;padding:.25rem .5rem">Bekeken op</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($detailViews as $vv): ?>
                                            <tr>
                                                <td style="padding:.3rem .5rem">
                                                    <?= htmlspecialchars($vv['video_title'], ENT_QUOTES, 'UTF-8') ?>
                                                </td>
                                                <td style="padding:.3rem .5rem;font-size:.82rem;white-space:nowrap">
                                                    <?= date('d-m-Y H:i', strtotime($vv['watched_at'])) ?>
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
document.querySelectorAll('.filter-drop').forEach(function(sel) {
    sel.addEventListener('change', function() {
        var params = new URLSearchParams(window.location.search);
        var param = this.getAttribute('data-param');
        var val   = this.value;
        if (val === '') { params.delete(param); }
        else { params.set(param, val); }
        window.location.search = params.toString();
    });
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
