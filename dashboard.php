<?php
require_once 'includes/bootstrap.php';
require_login();

$pdo = db();

$char = get_character();

if (!$char) {
    redirect('character-create.php');
}

// =========================
// AUTO LEVEL-UP SYSTEM
// =========================
function xp_required($level)
{
    return 100 + (($level - 1) * 40);
}

$xpNeeded = xp_required($char['level']);
$leveledUp = false;

while ($char['xp'] >= $xpNeeded) {
    $char['xp'] -= $xpNeeded;
    $char['level']++;
    $xpNeeded = xp_required($char['level']);
    $leveledUp = true;
}

if ($leveledUp) {
    $stmtUpdate = $pdo->prepare("
        UPDATE characters
        SET level = ?, xp = ?
        WHERE id = ?
    ");
    $stmtUpdate->execute([$char['level'], $char['xp'], $char['id']]);

    $_SESSION['flash'] = "ðŸŽ‰ Level Up! You are now level {$char['level']}.";
}

$gameMainClass = 'is-dashboard';
require_once 'templates/header.php';

// =========================
// ACTIVE WAR CHECK (GLOBAL)
// =========================
$stmtWar = $pdo->prepare("
    SELECT w.*, t.name AS turf_name, f1.name AS attacker_name, f2.name AS defender_name
    FROM turf_wars w
    JOIN turfs t ON t.id = w.turf_id
    JOIN factions f1 ON f1.id = w.attacker_faction_id
    JOIN factions f2 ON f2.id = w.defender_faction_id
    WHERE w.status = 'active'
    AND (w.attacker_faction_id = ? OR w.defender_faction_id = ?)
    LIMIT 1
");
$stmtWar->execute([$char['faction_id'], $char['faction_id']]);
$activeWarGlobal = $stmtWar->fetch();

// =========================
// TOP FACTION MEMBERS (TOP 5)
// =========================
$stmtMembers = $pdo->prepare("
    SELECT 
        c.name,
        c.level
    FROM characters c
    WHERE c.faction_id = ?
    ORDER BY c.level DESC, c.xp DESC
    LIMIT 5
");

$stmtMembers->execute([$char['faction_id']]);
$topMembers = $stmtMembers->fetchAll();

// Total Count
$stmtCount = $pdo->prepare("
    SELECT COUNT(*) FROM characters WHERE faction_id = ?
");
$stmtCount->execute([$char['faction_id']]);
$factionCount = $stmtCount->fetchColumn();

// =========================
// CITY CONTROL (ZONE BASED)
// =========================

$dashCityId = current_city_id();

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
$stmt->execute([$dashCityId, $dashCityId]);

$dominantRows = $stmt->fetchAll();

// Count zones
$zoneCounts = [];

foreach ($dominantRows as $row) {
    $fid = $row['faction_id'];
    if (!isset($zoneCounts[$fid])) {
        $zoneCounts[$fid] = 0;
    }
    $zoneCounts[$fid]++;
}

// Get factions for this city only
$stmtFactions = $pdo->prepare("SELECT id, name, color FROM factions WHERE city_id = ?");
$stmtFactions->execute([$dashCityId]);
$factions = $stmtFactions->fetchAll();

// Build data
$data = [];

foreach ($factions as $f) {

    $fid = $f['id'];
    $zones = $zoneCounts[$fid] ?? 0;

    // Tie-breaker (city-scoped)
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(tc.control_percent), 0)
        FROM turf_control tc
        JOIN turfs t ON t.id = tc.turf_id
        WHERE tc.faction_id = ? AND t.city_id = ?
    ");
    $stmt->execute([$fid, $dashCityId]);
    $control = (int)$stmt->fetchColumn();

    $data[] = [
        'id' => $fid,
        'name' => $f['name'],
        'color' => $f['color'],
        'zones' => $zones,
        'control' => $control
    ];
}

// Sort
usort($data, function($a, $b) {
    if ($a['zones'] === $b['zones']) {
        return $b['control'] <=> $a['control'];
    }
    return $b['zones'] <=> $a['zones'];
});

$leader = $data[0] ?? null;

// Find your faction
$yourFaction = null;
foreach ($data as $f) {
    if ($f['id'] == $char['faction_id']) {
        $yourFaction = $f;
        break;
    }
}

/**
 * ENERGY REGEN SYSTEM
 */
$now = time();
$last = strtotime($char['energy_updated_at']);
$secondsPassed = $now - $last;

$food = (int)$char['food'];

$regenRate = 300;

if ($food <= 0) {
    $regenRate = 999999;
} elseif ($food < 25) {
    $regenRate = 600;
} elseif ($food < 75) {
    $regenRate = 400;
}

$energyGained = floor($secondsPassed / $regenRate);

if ($energyGained > 0) {
    $newEnergy = min($char['energy'] + $energyGained, $char['energy_max']);

    $stmt = $pdo->prepare("
        UPDATE characters
        SET energy = ?, energy_updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$newEnergy, $char['id']]);

    $char['energy'] = $newEnergy;
}

// STARVATION DAMAGE
if ($char['food'] <= 0) {
    $pdo->prepare("
        UPDATE characters
        SET health = GREATEST(health - 1, 0)
        WHERE id = ?
    ")->execute([$char['id']]);

    $char['health'] = max($char['health'] - 1, 0);
}

/**
 * LOAD TURF
 */
$turf = null;
$rows = [];
$features = [];

if ($char['turf_id']) {
    $stmtTurf = $pdo->prepare("SELECT * FROM turfs WHERE id = ?");
    $stmtTurf->execute([$char['turf_id']]);
    $turf = $stmtTurf->fetch();

    decay_turf_heat($pdo, $char['turf_id']);

    // LOAD TURF FEATURES
    $stmtFeatures = $pdo->prepare("
        SELECT feature_type 
        FROM turf_features 
        WHERE turf_id = ?
    ");
    $stmtFeatures->execute([$char['turf_id']]);
    $features = $stmtFeatures->fetchAll(PDO::FETCH_COLUMN);

    $stmtControl = $pdo->prepare("
        SELECT tc.control_percent, f.name, f.color
        FROM turf_control tc
        JOIN factions f ON f.id = tc.faction_id
        WHERE tc.turf_id = ?
        ORDER BY tc.control_percent DESC
    ");
    $stmtControl->execute([$char['turf_id']]);
    $rows = $stmtControl->fetchAll();
}

// =========================
// RECENT ACTIVITY
// =========================
$activity = [];

$stmtWarAct = $pdo->prepare("
    SELECT twl.message, twl.created_at, 'war' AS source
    FROM turf_war_logs twl
    JOIN turf_wars tw ON tw.id = twl.war_id
    WHERE tw.attacker_faction_id = ? OR tw.defender_faction_id = ?
    ORDER BY twl.created_at DESC
    LIMIT 6
");
$stmtWarAct->execute([$char['faction_id'], $char['faction_id']]);
$activity = $stmtWarAct->fetchAll();

$stmtPersonal = $pdo->prepare("
    SELECT message, created_at, action_type AS source
    FROM game_logs
    WHERE character_id = ? AND status = 'success'
    ORDER BY created_at DESC
    LIMIT 4
");
$stmtPersonal->execute([$char['id']]);
$activity = array_merge($activity, $stmtPersonal->fetchAll());

usort($activity, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));
$activity = array_slice($activity, 0, 6);

function time_ago(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)    return 'just now';
    if ($diff < 3600)  return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    return floor($diff / 86400) . 'd ago';
}
?>

<?php if (!empty($_SESSION['flash'])): ?>
<div class="flash-box"><?= e($_SESSION['flash']) ?></div>
<?php unset($_SESSION['flash']); endif; ?>

<?php if ($activeWarGlobal):
    $isAttacker   = ($activeWarGlobal['attacker_faction_id'] == $char['faction_id']);
    $enemyFaction = $isAttacker ? $activeWarGlobal['defender_name'] : $activeWarGlobal['attacker_name'];
?>
<div class="war-banner">
    <span> At war with <strong><?= e($enemyFaction) ?></strong> in <strong><?= e($activeWarGlobal['turf_name']) ?></strong></span>
    <?php if ($activeWarGlobal['turf_id'] == $char['turf_id']): ?>
        <a href="war.php" class="text-xs bg-red-700 hover:bg-red-600 px-3 py-1 rounded text-white font-semibold">View War &rarr;</a>
    <?php else: ?>
        <form method="post" action="join_war.php">
            <?= csrf_input() ?>
            <button class="text-xs bg-red-700 hover:bg-red-600 px-3 py-1 rounded text-white font-semibold">Join War &rarr;</button>
        </form>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- DASHBOARD GRID -->
<div class="dash-grid">

<!-- ===== LEFT COLUMN ===== -->
<div class="dash-left">

    <!-- CHARACTER -->
    <div class="panel">
        <div class="flex items-center gap-3 mb-3">
            <img src="<?= e($char['icon_path']) ?>" style="width:36px;height:36px;">
            <div>
                <div class="font-bold text-sm leading-tight"><?= e($char['name']) ?></div>
                <div class="text-xs font-semibold" style="color:<?= e($char['color']) ?>"><?= e($char['faction_name']) ?></div>
            </div>
        </div>

        <div class="flex justify-between text-xs mb-1">
            <span class="text-zinc-400">Health</span>
            <span class="text-red-400 font-semibold"><?= $char['health'] ?>/<?= $char['health_max'] ?></span>
        </div>
        <div class="bar-track mb-2">
            <div class="bar-fill" style="width:<?= $char['health_max']>0?round(($char['health']/$char['health_max'])*100):0 ?>%;background:#ef4444"></div>
        </div>

        <div class="flex justify-between text-xs mb-1">
            <span class="text-zinc-400">XP</span>
            <span class="font-semibold"><?= $char['xp'] ?>/<?= $xpNeeded ?></span>
        </div>
        <div class="bar-track">
            <div class="bar-fill" style="width:<?= $xpNeeded>0?min(100,round(($char['xp']/$xpNeeded)*100)):0 ?>%;background:var(--accent)"></div>
        </div>

        <?php if ($char['health'] <= 0): ?>
            <div class="text-red-500 text-xs mt-2 font-semibold">Critically injured &mdash; actions blocked.</div>
        <?php elseif ($char['health'] / max(1, $char['health_max']) < 0.25): ?>
            <div class="text-red-400 text-xs mt-2">Badly injured &mdash; reduced success chance.</div>
        <?php endif; ?>
        <?php if ($char['food'] <= 0): ?>
            <div class="text-red-500 text-xs mt-1">Starving &mdash; no energy regen.</div>
        <?php elseif ($char['food'] < 25): ?>
            <div class="text-red-400 text-xs mt-1">Hungry &mdash; energy regen severely reduced.</div>
        <?php elseif ($char['food'] < 75): ?>
            <div class="text-yellow-400 text-xs mt-1">Getting hungry &mdash; slower regen.</div>
        <?php endif; ?>
    </div>

    <!-- LOCATION -->
    <div class="panel">
        <div class="panel-title">Location</div>
        <div class="font-semibold text-sm mb-2"><?= $turf ? e($turf['name']) : 'Unknown' ?></div>
        <?php if (!empty($features)): ?>
            <div class="flex flex-wrap gap-1">
                <?php foreach ($features as $feature): ?>
                    <?php if ($feature === 'cluckin_bell'): ?>
                        <span class="text-yellow-400 bg-yellow-900/20 border border-yellow-800/40 text-xs px-2 py-0.5 rounded inline-flex items-center gap-1">
                            <img src="/assets/img/cluckinbell.gif" class="h-3 w-auto"> Cluckin' Bell
                        </span>
                    <?php elseif ($feature === 'burger_shot'): ?>
                        <span class="text-orange-400 bg-orange-900/20 border border-orange-800/40 text-xs px-2 py-0.5 rounded inline-flex items-center gap-1">
                            <img src="/assets/img/burgershot.gif" class="h-3 w-auto"> Burger Shot
                        </span>
                    <?php elseif ($feature === 'pizza'): ?>
                        <span class="text-red-400 bg-red-900/20 border border-red-800/40 text-xs px-2 py-0.5 rounded inline-flex items-center gap-1">
                            <img src="/assets/img/pizza.gif" class="h-3 w-auto"> Pizza Stacks
                        </span>
                    <?php elseif ($feature === 'ammu_nation'): ?>
                        <span class="text-zinc-200 bg-zinc-700/30 border border-zinc-600/40 text-xs px-2 py-0.5 rounded inline-flex items-center gap-1">
                            <img src="/assets/img/ammu_nation.gif" class="h-3 w-auto"> Ammu-Nation
                        </span>
                    <?php elseif ($feature === 'hospital'): ?>
                        <span class="text-green-300 bg-green-900/20 border border-green-700/40 text-xs px-2 py-0.5 rounded inline-flex items-center gap-1">
                            <img src="/assets/img/hospital.gif" class="h-3 w-auto"> Hospital
                        </span>
                    <?php else: ?>
                        <span class="text-zinc-400 bg-zinc-800 text-xs px-2 py-0.5 rounded"><?= e($feature) ?></span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-zinc-500 text-xs">No resources here.</div>
        <?php endif; ?>
    </div>

    <!-- ATTRIBUTES -->
    <div class="panel">
        <div class="panel-title">Attributes</div>
        <div class="grid grid-cols-2 gap-x-3 gap-y-1 text-xs">
            <div class="flex justify-between"><span class="text-zinc-400">Strength</span><span><?= $char['strength'] ?></span></div>
            <div class="flex justify-between"><span class="text-zinc-400">Intelligence</span><span><?= $char['intelligence'] ?></span></div>
            <div class="flex justify-between"><span class="text-zinc-400">Endurance</span><span><?= $char['endurance'] ?></span></div>
            <div class="flex justify-between"><span class="text-zinc-400">Charisma</span><span><?= $char['charisma'] ?></span></div>
        </div>
    </div>

    <!-- CITY DOMINANCE -->
    <div class="panel">
        <div class="panel-title">City Dominance</div>
        <?php if ($leader): ?>
            <div class="flex justify-between items-center text-xs mb-1">
                <span> <span style="color:<?= e($leader['color']) ?>;font-weight:600"><?= e($leader['name']) ?></span></span>
                <span class="text-zinc-400"><?= $leader['zones'] ?> zone<?= $leader['zones']!=1?'s':'' ?></span>
            </div>
            <?php if ($yourFaction): ?>
                <div class="text-zinc-500 text-xs">Your faction: <?= $yourFaction['zones'] ?> zone<?= $yourFaction['zones']!=1?'s':'' ?></div>
            <?php endif; ?>
            <a href="dominance.php" class="text-xs text-zinc-600 hover:text-zinc-300 mt-2 block">Full breakdown &rarr;</a>
        <?php else: ?>
            <div class="text-zinc-500 text-xs">No data yet.</div>
        <?php endif; ?>
    </div>

    <!-- TOP FACTION MEMBERS -->
    <div class="panel">
        <div class="flex justify-between items-center mb-2">
            <div class="panel-title" style="margin-bottom:0"> Top Members</div>
            <span class="text-xs text-zinc-500"><?= $factionCount ?> total</span>
        </div>
        <?php if (!empty($topMembers)): ?>
            <div class="space-y-1">
                <?php foreach ($topMembers as $i => $member):
                    $isYou = $member['name'] === $char['name'];
                    $rank  = ['#1','#2','#3'][$i] ?? '&middot;';
                ?>
                    <div class="flex justify-between items-center text-xs <?= $isYou?'bg-zinc-800 rounded px-1.5 py-0.5':'' ?>">
                        <span><?= $rank ?> <?= e($member['name']) ?></span>
                        <span class="font-bold" style="color:var(--accent)">Lv <?= $member['level'] ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <a href="faction.php" class="block mt-2 text-center bg-zinc-800 hover:bg-zinc-700 py-1 rounded text-xs text-zinc-400">View Roster &rarr;</a>
    </div>

</div><!-- /.dash-left -->

<!-- ===== CENTER COLUMN ===== -->
<div class="dash-center">

    <!-- TURF CONTROL -->
    <div class="panel">
        <div class="panel-title">Turf Control &mdash; <?= $turf ? e($turf['name']) : 'N/A' ?></div>
        <?php if (!empty($rows)): ?>
            <?php foreach ($rows as $row): ?>
                <div class="flex justify-between text-xs mb-1">
                    <span><?= e($row['name']) ?></span>
                    <span class="font-semibold"><?= $row['control_percent'] ?>%</span>
                </div>
                <div class="bar-track mb-2">
                    <div class="bar-fill" style="width:<?= $row['control_percent'] ?>%;background:<?= e($row['color']) ?>"></div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="text-zinc-500 text-xs">No turf data.</div>
        <?php endif; ?>
    </div>

    <!-- QUICK ACTIONS -->
    <div class="panel">
        <div class="panel-title">Quick Actions</div>
        <div class="grid grid-cols-2 gap-2">
            <a href="actions.php" class="action-btn block text-center bg-orange-700 hover:bg-orange-600 px-3 py-2 rounded text-xs font-semibold"> Crime / Train</a>
            <a href="actions.php" class="action-btn block text-center bg-purple-800 hover:bg-purple-700 px-3 py-2 rounded text-xs font-semibold"> Capture Turf</a>
            <a href="turf.php"    class="action-btn block text-center bg-blue-800 hover:bg-blue-700 px-3 py-2 rounded text-xs font-semibold"> Turf Map</a>
            <button id="open-move-modal" class="action-btn bg-zinc-700 hover:bg-zinc-600 px-3 py-2 rounded text-xs font-semibold"> Move Location</button>
        </div>
    </div>

    <!-- RECENT ACTIVITY (fills remaining height) -->
    <div class="panel dash-activity">
        <div class="panel-title">Recent Activity</div>
        <?php if (empty($activity)): ?>
            <div class="text-zinc-500 text-xs">No activity yet.</div>
        <?php else: ?>
            <div class="activity-list space-y-1.5 pr-1">
                <?php foreach ($activity as $entry):
                    $isWar = ($entry['source'] === 'war');
                    $dot   = $isWar ? 'bg-red-500' : 'bg-orange-400';
                ?>
                    <div class="flex items-start gap-2 text-xs">
                        <span class="mt-1.5 w-1.5 h-1.5 rounded-full flex-shrink-0 <?= $dot ?>"></span>
                        <span class="text-zinc-300 flex-1"><?= e($entry['message']) ?></span>
                        <span class="text-zinc-600 whitespace-nowrap ml-1"><?= time_ago($entry['created_at']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>


</div><!-- /.dash-center -->

<!-- ===== RIGHT COLUMN &mdash; CHAT ===== -->
<div class="dash-right">

    <div class="panel chat-panel">
        <div class="panel-title">Chat</div>

        <div class="flex gap-1 mb-2">
            <button class="chat-tab bg-orange-600 text-xs px-3 py-1 rounded font-semibold" data-channel="global">Global</button>
            <button class="chat-tab bg-zinc-700 text-xs px-3 py-1 rounded font-semibold" data-channel="faction">Faction</button>
            <button class="chat-tab bg-zinc-700 text-xs px-3 py-1 rounded font-semibold" data-channel="zone">Zone</button>
        </div>

        <div id="chat-box" class="bg-zinc-900/70 text-xs p-2 rounded mb-2"></div>

        <div class="flex gap-1.5">
            <input id="chat-input" maxlength="200" placeholder="Type message..."
                class="flex-1 bg-zinc-800 border border-zinc-700/60 rounded px-2 py-1.5 text-xs text-white outline-none focus:border-zinc-500">
            <button id="chat-send"
                class="bg-orange-600 hover:bg-orange-500 text-white text-xs px-3 rounded font-semibold action-btn">Send</button>
        </div>

        <div class="flex justify-between mt-1">
            <div id="chat-error" class="text-xs text-red-400 hidden"></div>
            <div class="text-xs text-zinc-600 ml-auto"><span id="chat-count">0</span>/200</div>
        </div>
    </div>

</div><!-- /.dash-right -->

</div><!-- /.dash-grid -->

<!-- MOVE MODAL -->
<div id="move-modal" class="move-modal-overlay">
    <div class="move-modal">
        <div class="move-modal-header">
            <span class="move-modal-title"> Move Location</span>
            <button class="move-modal-close" id="close-move-modal"> Close</button>
        </div>
        <div class="text-xs text-zinc-500 -mt-2 mb-1">Select a turf to travel to.</div>
        <div class="move-modal-grid">
            <?php
            $stmtMove = $pdo->prepare("SELECT id, name FROM turfs WHERE city_id = ? ORDER BY name ASC");
            $stmtMove->execute([current_city_id()]);
            $moveTurfs = $stmtMove->fetchAll();
            foreach ($moveTurfs as $t) {
                if ($t['id'] == $char['turf_id']) {
                    echo "<button class='bg-green-900/40 border border-green-700/40 text-green-300 p-2 rounded text-xs cursor-default' disabled>" . e($t['name']) . "  (current)</button>";
                } else {
                    echo "<button class='action-btn move-btn bg-zinc-800 hover:bg-zinc-700 border border-zinc-700/30 p-2 rounded text-xs' data-turf-id='" . $t['id'] . "'>" . e($t['name']) . "</button>";
                }
            }
            ?>
        </div>
    </div>
</div>

<script>
(function() {
    const overlay = document.getElementById('move-modal');
    document.getElementById('open-move-modal')?.addEventListener('click', () => overlay.classList.add('open'));
    document.getElementById('close-move-modal')?.addEventListener('click', () => overlay.classList.remove('open'));
    overlay?.addEventListener('click', e => { if (e.target === overlay) overlay.classList.remove('open'); });
})();
</script>

<?php require_once 'templates/footer.php'; ?>
