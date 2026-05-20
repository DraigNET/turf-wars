<?php
require_once 'includes/bootstrap.php';
require_login();

$pdo = db();

$stmtUser = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
$stmtUser->execute([user_id()]);
$user = $stmtUser->fetch();

$canModerateChat = ($user['is_admin'] ?? 0) == 1;

/**
 * Load current character (for highlight)
 */
$stmt = $pdo->prepare("SELECT id FROM characters WHERE user_id = ?");
$stmt->execute([user_id()]);
$char = $stmt->fetch();

if (is_post()) {
    verify_csrf();

    if (!$canModerateChat) {
        exit('Access denied');
    }

    $action = $_POST['mod_action'] ?? '';
    $targetUserId = (int)($_POST['target_user_id'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');

    if ($targetUserId > 0) {
        if ($action === 'mute_chat') {
            $stmtMute = $pdo->prepare("
                INSERT INTO chat_mutes (user_id, muted_by_user_id, reason, expires_at)
                VALUES (?, ?, ?, NULL)
            ");
            $stmtMute->execute([
                $targetUserId,
                user_id(),
                $reason !== '' ? $reason : 'No reason provided'
            ]);
        }

        if ($action === 'unmute_chat') {
            $stmtUnmute = $pdo->prepare("
                DELETE FROM chat_mutes
                WHERE user_id = ?
            ");
            $stmtUnmute->execute([$targetUserId]);
        }
    }

    redirect('players.php?page=' . max(1, (int)($_GET['page'] ?? 1)));
}

$cityId = current_city_id();

/**
 * Pagination
 */
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;

/**
 * Total players in this city
 */
$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM characters WHERE city_id = ?");
$totalStmt->execute([$cityId]);
$total = (int)$totalStmt->fetchColumn();
$totalPages = max(1, ceil($total / $limit));

/**
 * Top 5 players in this city
 */
$stmtTop = $pdo->prepare("
    SELECT
        c.id,
        c.user_id,
        c.name,
        c.level,
        u.last_ip,
        f.name AS faction_name,
        t.name AS turf_name,
        EXISTS (
            SELECT 1
            FROM chat_mutes cm
            WHERE cm.user_id = c.user_id
            AND (cm.expires_at IS NULL OR cm.expires_at > NOW())
        ) AS is_chat_muted
    FROM characters c
    LEFT JOIN users u ON u.id = c.user_id
    LEFT JOIN factions f ON f.id = c.faction_id
    LEFT JOIN turfs t ON t.id = c.turf_id
    WHERE c.city_id = ?
    ORDER BY c.level DESC, c.xp DESC
    LIMIT 5
");
$stmtTop->execute([$cityId]);
$topPlayers = $stmtTop->fetchAll();

/**
 * All players (paginated) in this city
 */
$stmtPlayers = $pdo->prepare("
    SELECT
        c.id,
        c.user_id,
        c.name,
        c.level,
        u.last_ip,
        f.name AS faction_name,
        t.name AS turf_name,
        EXISTS (
            SELECT 1
            FROM chat_mutes cm
            WHERE cm.user_id = c.user_id
            AND (cm.expires_at IS NULL OR cm.expires_at > NOW())
        ) AS is_chat_muted
    FROM characters c
    LEFT JOIN users u ON u.id = c.user_id
    LEFT JOIN factions f ON f.id = c.faction_id
    LEFT JOIN turfs t ON t.id = c.turf_id
    WHERE c.city_id = ?
    ORDER BY c.level DESC, c.xp DESC
    LIMIT ? OFFSET ?
");
$stmtPlayers->bindValue(1, $cityId, PDO::PARAM_INT);
$stmtPlayers->bindValue(2, $limit, PDO::PARAM_INT);
$stmtPlayers->bindValue(3, $offset, PDO::PARAM_INT);
$stmtPlayers->execute();
$players = $stmtPlayers->fetchAll();

$char = get_character();

if (!$char) {
    redirect('character-create.php');
}

require_once 'templates/header.php';
?>

<div class="max-w-6xl mx-auto p-6">

    <!-- TOP PLAYERS -->
    <div class="panel p-5 rounded-xl mb-6">
        <h2 class="text-xl font-bold mb-4">🏆 Top Players</h2>

        <div class="space-y-2">
            <?php foreach ($topPlayers as $i => $p): ?>
                <?php $rank = $i + 1; ?>
                <?php $isYou = $char && $p['id'] == $char['id']; ?>

                <div class="flex justify-between items-center p-3 rounded 
                    <?= $isYou ? 'bg-zinc-800 border border-orange-500' : 'bg-zinc-900' ?>">

                    <div>
                        <div class="font-semibold">
                            #<?= $rank ?> <?= e($p['name']) ?>
                        </div>
                        <div class="text-xs text-zinc-500">
                            <?= e($p['faction_name'] ?? 'No Faction') ?> • 
                            <?= e($p['turf_name'] ?? 'No Turf') ?>
                        </div>

                        <?php if ($canModerateChat): ?>
                            <div class="text-xs text-zinc-400 mt-1">
                                IP: <?= e($p['last_ip'] ?? 'Unknown') ?>
                            </div>

                            <div class="text-xs mt-1">
                                <?php if (!empty($p['is_chat_muted'])): ?>
                                    <span class="text-red-400 font-semibold">Chat Muted</span>
                                <?php else: ?>
                                    <span class="text-green-400 font-semibold">Chat Active</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="text-right">
                        <div class="text-orange-400 font-bold mb-2">
                            Lv <?= $p['level'] ?>
                        </div>

                        <?php if ($canModerateChat): ?>
                            <form method="post" class="space-y-2">
                                <?= csrf_input() ?>
                                <input type="hidden" name="target_user_id" value="<?= (int)$p['user_id'] ?>">

                                <input
                                    type="text"
                                    name="reason"
                                    placeholder="Mute reason"
                                    class="bg-zinc-800 border border-zinc-700 rounded px-2 py-1 text-xs w-40"
                                >

                                <?php if (!empty($p['is_chat_muted'])): ?>
                                    <button
                                        type="submit"
                                        name="mod_action"
                                        value="unmute_chat"
                                        class="block w-full bg-green-600 hover:bg-green-500 px-2 py-1 rounded text-xs"
                                    >
                                        Unmute Chat
                                    </button>
                                <?php else: ?>
                                    <button
                                        type="submit"
                                        name="mod_action"
                                        value="mute_chat"
                                        class="block w-full bg-red-600 hover:bg-red-500 px-2 py-1 rounded text-xs"
                                    >
                                        Mute Chat
                                    </button>
                                <?php endif; ?>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ALL PLAYERS -->
    <div class="panel p-5 rounded-xl">

        <h2 class="text-xl font-bold mb-4">👥 All Players</h2>

        <div class="space-y-2">
            <?php foreach ($players as $i => $p): ?>
                <?php $rank = $offset + $i + 1; ?>
                <?php $isYou = $char && $p['id'] == $char['id']; ?>

                <div class="flex justify-between items-center p-3 rounded 
                    <?= $isYou ? 'bg-zinc-800 border border-orange-500' : 'bg-zinc-900' ?>">

                    <div>
                        <div class="font-semibold">
                            #<?= $rank ?> <?= e($p['name']) ?>
                        </div>
                        <div class="text-xs text-zinc-500">
                            <?= e($p['faction_name'] ?? 'No Faction') ?> • 
                            <?= e($p['turf_name'] ?? 'No Turf') ?>
                        </div>

                        <?php if ($canModerateChat): ?>
                            <div class="text-xs text-zinc-400 mt-1">
                                IP: <?= e($p['last_ip'] ?? 'Unknown') ?>
                            </div>

                            <div class="text-xs mt-1">
                                <?php if (!empty($p['is_chat_muted'])): ?>
                                    <span class="text-red-400 font-semibold">Chat Muted</span>
                                <?php else: ?>
                                    <span class="text-green-400 font-semibold">Chat Active</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="text-right">
                        <div class="text-orange-400 font-bold mb-2">
                            Lv <?= $p['level'] ?>
                        </div>

                        <?php if ($canModerateChat): ?>
                            <form method="post" class="space-y-2">
                                <?= csrf_input() ?>
                                <input type="hidden" name="target_user_id" value="<?= (int)$p['user_id'] ?>">

                                <input
                                    type="text"
                                    name="reason"
                                    placeholder="Mute reason"
                                    class="bg-zinc-800 border border-zinc-700 rounded px-2 py-1 text-xs w-40"
                                >

                                <?php if (!empty($p['is_chat_muted'])): ?>
                                    <button
                                        type="submit"
                                        name="mod_action"
                                        value="unmute_chat"
                                        class="block w-full bg-green-600 hover:bg-green-500 px-2 py-1 rounded text-xs"
                                    >
                                        Unmute Chat
                                    </button>
                                <?php else: ?>
                                    <button
                                        type="submit"
                                        name="mod_action"
                                        value="mute_chat"
                                        class="block w-full bg-red-600 hover:bg-red-500 px-2 py-1 rounded text-xs"
                                    >
                                        Mute Chat
                                    </button>
                                <?php endif; ?>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- PAGINATION -->
        <div class="flex justify-between mt-6 text-sm">

            <div>
                Page <?= $page ?> / <?= $totalPages ?>
            </div>

            <div class="flex gap-2">

                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>" 
                       class="px-3 py-1 bg-zinc-800 rounded hover:bg-zinc-700">
                        Prev
                    </a>
                <?php endif; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>" 
                       class="px-3 py-1 bg-zinc-800 rounded hover:bg-zinc-700">
                        Next
                    </a>
                <?php endif; ?>

            </div>

        </div>

    </div>

</div>

<?php require_once 'templates/footer.php'; ?>
