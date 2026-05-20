<?php
require_once 'includes/bootstrap.php';
require_login();

if (!is_post()) {
    redirect('dashboard.php');
}

verify_csrf();

$pdo  = db();
$char = get_character();

if (!$char) {
    redirect('dashboard.php');
}

// Find the active war for this player's faction
$stmt = $pdo->prepare("
    SELECT tw.*, t.name AS turf_name
    FROM turf_wars tw
    JOIN turfs t ON t.id = tw.turf_id
    WHERE tw.status = 'active'
    AND (tw.attacker_faction_id = ? OR tw.defender_faction_id = ?)
    LIMIT 1
");
$stmt->execute([$char['faction_id'], $char['faction_id']]);
$war = $stmt->fetch();

if (!$war) {
    redirect('dashboard.php');
}

// Move player to the war turf if not already there
if ($char['turf_id'] != $war['turf_id']) {
    $pdo->prepare("
        UPDATE characters SET turf_id = ? WHERE id = ?
    ")->execute([$war['turf_id'], $char['id']]);
}

// Ensure they're enrolled as a participant (covers late joiners)
$stmtCheck = $pdo->prepare("
    SELECT id FROM turf_war_participants
    WHERE war_id = ? AND char_id = ?
    LIMIT 1
");
$stmtCheck->execute([$war['id'], $char['id']]);

if (!$stmtCheck->fetch()) {
    $hp = (int)($char['health_max'] ?? 100);
    $pdo->prepare("
        INSERT INTO turf_war_participants (war_id, char_id, faction_id, current_hp, max_hp)
        VALUES (?, ?, ?, ?, ?)
    ")->execute([$war['id'], $char['id'], $char['faction_id'], $hp, $hp]);
}

redirect('war.php');
