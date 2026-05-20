<?php
require_once 'includes/bootstrap.php';
require_login();

$pdo = db();
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if (!is_post()) {
    redirect('war.php');
}

verify_csrf();

$char = get_character();

if (!$char || !$char['turf_id']) {
    redirect('actions.php');
}

// =========================
// NORMALISE TURF
// =========================
function normalise_turf($pdo, $turfId) {

    $stmt = $pdo->prepare("
        SELECT faction_id, control_percent
        FROM turf_control
        WHERE turf_id = ?
    ");
    $stmt->execute([$turfId]);
    $rows = $stmt->fetchAll();

    $total = array_sum(array_column($rows, 'control_percent'));

    if ($total <= 0) {
        return;
    }

    foreach ($rows as $row) {

        $newPercent = round(($row['control_percent'] / $total) * 100);

        $pdo->prepare("
            UPDATE turf_control
            SET control_percent = ?
            WHERE turf_id = ? AND faction_id = ?
        ")->execute([$newPercent, $turfId, $row['faction_id']]);
    }
}

// =========================
// LOAD ACTIVE WAR
// =========================
$stmt = $pdo->prepare("
    SELECT * FROM turf_wars
    WHERE turf_id = ? AND status = 'active'
    LIMIT 1
");
$stmt->execute([$char['turf_id']]);
$war = $stmt->fetch();

if (!$war) {
    redirect('actions.php');
}

// =========================
// LOAD PLAYER PARTICIPATION
// =========================
$stmt = $pdo->prepare("
    SELECT * FROM turf_war_participants
    WHERE war_id = ? AND char_id = ?
    LIMIT 1
");
$stmt->execute([$war['id'], $char['id']]);
$player = $stmt->fetch();

if (!$player || !$player['is_alive']) {
    redirect('war.php');
}

// =========================
// COOLDOWN CHECK
// =========================
$cooldown = 5; // seconds
$now = time();

if ($char['last_war_attack'] > 0 && ($now - $char['last_war_attack']) < $cooldown) {
    redirect('war.php');
}

// =========================
// DETERMINE SIDES
// =========================
$isAttacker = ($char['faction_id'] == $war['attacker_faction_id']);

$enemyFactionId = $isAttacker
    ? $war['defender_faction_id']
    : $war['attacker_faction_id'];

// =========================
// BEGIN TRANSACTION
// =========================
$pdo->beginTransaction();

// =========================
// GENERATE POINTS
// =========================
$points = rand(8, 18) + (int)floor($char['strength'] / 5) + (int)($char['war_points_bonus'] ?? 0);

// =========================
// ADD SCORE
// =========================
if ($isAttacker) {
    $pdo->prepare("
        UPDATE turf_wars
        SET attacker_score = attacker_score + ?
        WHERE id = ?
    ")->execute([$points, $war['id']]);
} else {
    $pdo->prepare("
        UPDATE turf_wars
        SET defender_score = defender_score + ?
        WHERE id = ?
    ")->execute([$points, $war['id']]);
}

// =========================
// RANDOM TARGET
// =========================
$stmtEnemy = $pdo->prepare("
    SELECT p.id, p.char_id, p.current_hp, c.name AS target_name, c.endurance
    FROM turf_war_participants p
    JOIN characters c ON c.id = p.char_id
    WHERE p.war_id = ?
    AND p.faction_id = ?
    AND p.is_alive = 1
    ORDER BY RAND()
    LIMIT 1
");
$stmtEnemy->execute([$war['id'], $enemyFactionId]);
$target = $stmtEnemy->fetch();

// =========================
// DEAL DAMAGE
// =========================
$weaponDamage = (int)($char['weapon_damage'] ?? 10);
$damage = 0;
$killed = false;
$newHp  = null;

if ($target) {
    $damage = max(1,
        rand(5, 12)
        + $weaponDamage
        + (int)floor($char['strength'] / 8)
        - (int)floor((int)($target['endurance'] ?? 10) / 8)
    );

    $newHp = max(0, (int)$target['current_hp'] - $damage);

    if ($newHp <= 0) {
        $pdo->prepare("
            UPDATE turf_war_participants
            SET is_alive = 0, current_hp = 0, killed_at = NOW()
            WHERE id = ?
        ")->execute([$target['id']]);
        $killed = true;
    } else {
        $pdo->prepare("
            UPDATE turf_war_participants
            SET current_hp = ?
            WHERE id = ?
        ")->execute([$newHp, $target['id']]);
    }
}

// =========================
// UPDATE PLAYER COOLDOWN
// =========================
$pdo->prepare("
    UPDATE characters
    SET last_war_attack = ?
    WHERE id = ?
")->execute([$now, $char['id']]);

// =========================
// LOG EVENT
// =========================
if ($target && $killed) {
    $message = "{$char['name']} dealt {$damage} damage and eliminated {$target['target_name']}! (+{$points} pts)";
} elseif ($target) {
    $message = "{$char['name']} dealt {$damage} damage to {$target['target_name']} (+{$points} pts)";
} else {
    $message = "{$char['name']} gained +{$points} points";
}

$pdo->prepare("
    INSERT INTO turf_war_logs (war_id, message)
    VALUES (?, ?)
")->execute([$war['id'], $message]);

// =========================
// CHECK WIN CONDITIONS
// =========================
$stmt = $pdo->prepare("
    SELECT attacker_score, defender_score, target_score
    FROM turf_wars
    WHERE id = ?
");
$stmt->execute([$war['id']]);
$updatedWar = $stmt->fetch();

$winnerFactionId = null;

// Score win
if ($updatedWar['attacker_score'] >= $updatedWar['target_score']) {
    $winnerFactionId = $war['attacker_faction_id'];
}
elseif ($updatedWar['defender_score'] >= $updatedWar['target_score']) {
    $winnerFactionId = $war['defender_faction_id'];
}

// Death wipe check
if (!$winnerFactionId) {

    $stmt = $pdo->prepare("
        SELECT faction_id, COUNT(*) as alive
        FROM turf_war_participants
        WHERE war_id = ? AND is_alive = 1
        GROUP BY faction_id
    ");
    $stmt->execute([$war['id']]);
    $aliveCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Ensure both factions are checked
    $attackerAlive = $aliveCounts[$war['attacker_faction_id']] ?? 0;
    $defenderAlive = $aliveCounts[$war['defender_faction_id']] ?? 0;

    if ($attackerAlive == 0) {
        $winnerFactionId = $war['defender_faction_id'];
    }
    elseif ($defenderAlive == 0) {
        $winnerFactionId = $war['attacker_faction_id'];
    }
}

// =========================
// END WAR IF WINNER
// =========================
if ($winnerFactionId) {

    $pdo->prepare("
        UPDATE turf_wars
        SET status = 'finished',
            winner_faction_id = ?,
            ended_at = NOW()
        WHERE id = ?
    ")->execute([$winnerFactionId, $war['id']]);

    // Set new turf owner
    $pdo->prepare("
        UPDATE turfs
        SET faction_id = ?
        WHERE id = ?
    ")->execute([$winnerFactionId, $char['turf_id']]);

    $loserFactionId = ($winnerFactionId == $war['attacker_faction_id'])
        ? $war['defender_faction_id']
        : $war['attacker_faction_id'];

    // Fetch current control for winner and loser
    $stmtCtrl = $pdo->prepare("SELECT faction_id, control_percent FROM turf_control WHERE turf_id = ?");
    $stmtCtrl->execute([$char['turf_id']]);
    $controls = $stmtCtrl->fetchAll(PDO::FETCH_KEY_PAIR);

    $loserCurrent = (int)($controls[$loserFactionId] ?? 0);
    $winnerCurrent = (int)($controls[$winnerFactionId] ?? 0);

    // Loser retains a floor: 35% of current control, minimum 15%
    $loserNew = max(15, (int)round($loserCurrent * 0.35));

    // Winner gains a +20% boost from the victory, capped at 70% pre-normalise
    $winnerNew = min(70, $winnerCurrent + 20);

    $pdo->prepare("
        UPDATE turf_control SET control_percent = ? WHERE turf_id = ? AND faction_id = ?
    ")->execute([$loserNew, $char['turf_id'], $loserFactionId]);

    $pdo->prepare("
        UPDATE turf_control SET control_percent = ? WHERE turf_id = ? AND faction_id = ?
    ")->execute([$winnerNew, $char['turf_id'], $winnerFactionId]);

    // Apply cooldown
    $pdo->prepare("
        UPDATE turfs
        SET war_cooldown_until = DATE_ADD(NOW(), INTERVAL 24 HOUR)
        WHERE id = ?
    ")->execute([$char['turf_id']]);

    // Normalise turf control after war
    normalise_turf($pdo, $char['turf_id']);

    // Reset all participants (respawn everyone, restore HP)
    $pdo->prepare("
        UPDATE turf_war_participants
        SET is_alive = 1,
            current_hp = max_hp,
            killed_at = NULL
        WHERE war_id = ?
    ")->execute([$war['id']]);

    $pdo->prepare("
        UPDATE characters
        SET last_war_attack = 0
        WHERE faction_id IN (?, ?)
    ")->execute([
        $war['attacker_faction_id'],
        $war['defender_faction_id']
    ]);
}

$pdo->commit();

// =========================
// RESPOND
// =========================
if ($isAjax) {
    $stmt = $pdo->prepare("SELECT attacker_score, defender_score, status FROM turf_wars WHERE id = ?");
    $stmt->execute([$war['id']]);
    $finalWar = $stmt->fetch();

    $mySide = ($char['faction_id'] == $war['attacker_faction_id']) ? 'attacker' : 'defender';

    header('Content-Type: application/json');
    echo json_encode([
        'points'         => $points,
        'damage'         => $damage,
        'killed'         => $killed,
        'target_name'    => $target['target_name'] ?? null,
        'target_hp'      => $newHp,
        'message'        => $message,
        'attacker_score' => (int)$finalWar['attacker_score'],
        'defender_score' => (int)$finalWar['defender_score'],
        'target_score'   => (int)$war['target_score'],
        'war_ended'      => $finalWar['status'] === 'finished',
        'winner'         => $winnerFactionId,
        'my_side'        => $mySide,
        'cooldown'       => 5,
    ]);
    exit;
}

redirect('war.php');