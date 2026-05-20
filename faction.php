<?php
require_once 'includes/bootstrap.php';
require_login();

$pdo = db();

$char = get_character();

if (!$char) {
    redirect('character-create.php');
}

/**
 * Pagination
 */
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;

/**
 * Total members
 */
$stmtCount = $pdo->prepare("
    SELECT COUNT(*)
    FROM characters
    WHERE faction_id = ? AND city_id = ?
");
$stmtCount->execute([$char['faction_id'], current_city_id()]);
$total = (int)$stmtCount->fetchColumn();

$totalPages = max(1, ceil($total / $limit));

/**
 * Fetch members
 */
$stmtMembers = $pdo->prepare("
    SELECT 
        c.id,
        c.name,
        c.level,
        c.turf_id,
        t.name AS turf_name
    FROM characters c
    LEFT JOIN turfs t ON t.id = c.turf_id
    WHERE c.faction_id = ? AND c.city_id = ?
    ORDER BY c.level DESC, c.xp DESC
    LIMIT ? OFFSET ?
");
$stmtMembers->bindValue(1, (int)$char['faction_id'], PDO::PARAM_INT);
$stmtMembers->bindValue(2, current_city_id(), PDO::PARAM_INT);
$stmtMembers->bindValue(3, $limit, PDO::PARAM_INT);
$stmtMembers->bindValue(4, $offset, PDO::PARAM_INT);
$stmtMembers->execute();
$members = $stmtMembers->fetchAll();

require_once 'templates/header.php';
?>

<div class="max-w-6xl mx-auto p-6">

    <!-- HEADER -->
    <div class="panel p-5 rounded-xl mb-6">
        <h1 class="text-2xl font-bold">
            👥 <?= e($char['faction_name']) ?>
        </h1>
        <p class="text-zinc-400 text-sm">
            <?= $total ?> members
        </p>
    </div>

    <!-- MEMBER LIST -->
    <div class="panel p-4 rounded-xl">

        <?php if (empty($members)): ?>
            <p class="text-zinc-400 text-sm">No members found.</p>
        <?php endif; ?>

        <div class="space-y-2">

            <?php foreach ($members as $i => $member): ?>

                <?php
                $isYou = $member['id'] == $char['id'];
                ?>

                <div class="flex justify-between items-center p-3 rounded 
                    <?= $isYou ? 'bg-zinc-800 border border-orange-500' : 'bg-zinc-900' ?>">

                    <div>
                        <div class="font-semibold text-white">
                            <?= e($member['name']) ?>
                        </div>

                        <div class="text-xs text-zinc-500">
                            <?= $member['turf_name'] ? e($member['turf_name']) : 'No Turf' ?>
                        </div>
                    </div>

                    <div class="text-right">
                        <div class="text-orange-400 font-bold">
                            Lv <?= $member['level'] ?>
                        </div>
                    </div>

                </div>

            <?php endforeach; ?>

        </div>

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

<?php require_once 'templates/footer.php'; ?>
