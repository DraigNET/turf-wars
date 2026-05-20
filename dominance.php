<?php
require_once 'includes/bootstrap.php';
require_login();

$pdo = db();

$char = get_character();

if (!$char) {
    redirect('character-create.php');
}

// =========================
// ZONE OWNERSHIP SYSTEM
// =========================

$cityId = current_city_id();

// Get dominant faction per turf (current city only)
$stmt = $pdo->prepare("
    SELECT tc.turf_id, tc.faction_id, tc.control_percent
    FROM turf_control tc
    JOIN turfs t ON t.id = tc.turf_id
    INNER JOIN (
        SELECT tc2.turf_id, MAX(tc2.control_percent) AS max_control
        FROM turf_control tc2
        JOIN turfs t2 ON t2.id = tc2.turf_id
        WHERE t2.city_id = ?
        GROUP BY tc2.turf_id
    ) tmax
        ON tc.turf_id = tmax.turf_id
        AND tc.control_percent = tmax.max_control
    WHERE t.city_id = ?
");
$stmt->execute([$cityId, $cityId]);

$dominantRows = $stmt->fetchAll();

// Count zones per faction
$zoneCounts = [];

foreach ($dominantRows as $row) {
    $fid = $row['faction_id'];
    if (!isset($zoneCounts[$fid])) {
        $zoneCounts[$fid] = 0;
    }
    $zoneCounts[$fid]++;
}

$totalZones = count(array_unique(array_column($dominantRows, 'turf_id')));

// Get faction info for current city only
$stmtFactions = $pdo->prepare("SELECT id, name, color FROM factions WHERE city_id = ?");
$stmtFactions->execute([$cityId]);
$factions = $stmtFactions->fetchAll();

// Build final data
$data = [];

foreach ($factions as $f) {

    $fid = $f['id'];

    // Zone count
    $zones = $zoneCounts[$fid] ?? 0;

    // Total influence within this city (tie-breaker)
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(tc.control_percent), 0)
        FROM turf_control tc
        JOIN turfs t ON t.id = tc.turf_id
        WHERE tc.faction_id = ? AND t.city_id = ?
    ");
    $stmt->execute([$fid, $cityId]);
    $totalControl = (int)$stmt->fetchColumn();

    $data[] = [
        'id' => $fid,
        'name' => $f['name'],
        'color' => $f['color'],
        'zones' => $zones,
        'control' => $totalControl
    ];
}

// Sort:
// 1. Zones DESC
// 2. Control DESC (tie-breaker)
usort($data, function($a, $b) {
    if ($a['zones'] === $b['zones']) {
        return $b['control'] <=> $a['control'];
    }
    return $b['zones'] <=> $a['zones'];
});

$leader = $data[0] ?? null;

// Max zones (for progress bar scaling)
$maxZones = max(array_column($data, 'zones')) ?: 1;

require_once 'templates/header.php';
?>

<div class="max-w-5xl mx-auto p-6 space-y-6">

    <!-- HEADER -->
    <div class="panel p-6 rounded-xl">
        <h1 class="text-2xl font-bold mb-1">City Control</h1>
        <p class="text-zinc-400 text-sm">
            Which faction dominates Los Santos?
        </p>
    </div>

    <!-- LEADER -->
    <?php if ($leader): ?>
        <div class="panel p-5 rounded-xl flex items-center justify-between">

            <div>
                <p class="text-xs text-zinc-400 mb-1">Current Leader</p>

                <p class="text-xl font-bold flex items-center gap-2">
                    <span>👑</span>
                    <span style="color: <?= e($leader['color']) ?>">
                        <?= e($leader['name']) ?>
                    </span>
                </p>

                <p class="text-sm text-zinc-400 mt-1">
                    Controls <?= $leader['zones'] ?>/<?= $totalZones ?> zone<?= $leader['zones'] != 1 ? 's' : '' ?>
                </p>
            </div>

        </div>
    <?php endif; ?>

    <!-- FACTION LIST -->
    <div class="panel p-5 rounded-xl">
        <p class="panel-title mb-4">Faction Rankings</p>

        <?php if (!empty($data)): ?>

            <div class="space-y-4">

                <?php foreach ($data as $i => $f): ?>

                    <?php
                    $rankIcon = '';
                    if ($i === 0) $rankIcon = '👑';
                    elseif ($i === 1) $rankIcon = '🥈';
                    elseif ($i === 2) $rankIcon = '🥉';

                    $barWidth = $totalZones > 0 ? ($f['zones'] / $totalZones) * 100 : 0;
                    ?>

                    <div>

                        <!-- NAME + ZONES -->
                        <div class="flex justify-between text-sm mb-1">
                            <span>
                                <?= $rankIcon ?> 
                                <span style="color: <?= e($f['color']) ?>">
                                    <?= e($f['name']) ?>
                                </span>
                            </span>

                            <span class="text-zinc-300 font-semibold">
                                <?= $f['zones'] ?>/<?= $totalZones ?> zone<?= $f['zones'] != 1 ? 's' : '' ?>
                            </span>
                        </div>

                        <!-- BAR -->
                        <div class="w-full bg-zinc-800 h-3 rounded">
                            <div 
                                class="h-3 rounded"
                                style="width: <?= $barWidth ?>%; background: <?= e($f['color']) ?>;">
                            </div>
                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <p class="text-zinc-400 text-sm">
                No dominance data available.
            </p>

        <?php endif; ?>

    </div>

</div>

<?php require_once 'templates/footer.php'; ?>