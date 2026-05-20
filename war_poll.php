<?php
require_once 'includes/bootstrap.php';
require_login();

header('Content-Type: application/json');

$pdo  = db();
$char = get_character();

if (!$char || !$char['turf_id']) {
    echo json_encode(['error' => 'no_char']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM turf_wars WHERE turf_id = ? AND status = 'active' LIMIT 1");
$stmt->execute([$char['turf_id']]);
$war = $stmt->fetch();

if (!$war) {
    echo json_encode(['war_ended' => true]);
    exit;
}

// Alive / dead counts
$stmtCounts = $pdo->prepare("
    SELECT faction_id,
           SUM(is_alive = 1) as alive,
           SUM(is_alive = 0) as dead
    FROM turf_war_participants
    WHERE war_id = ?
    GROUP BY faction_id
");
$stmtCounts->execute([$war['id']]);
$counts = [];
foreach ($stmtCounts->fetchAll() as $row) {
    $counts[$row['faction_id']] = $row;
}

// My alive status and HP
$stmtMe = $pdo->prepare("SELECT is_alive, current_hp, max_hp FROM turf_war_participants WHERE war_id = ? AND char_id = ? LIMIT 1");
$stmtMe->execute([$war['id'], $char['id']]);
$me = $stmtMe->fetch();

// Latest log (last 20)
$stmtLogs = $pdo->prepare("
    SELECT message FROM turf_war_logs WHERE war_id = ? ORDER BY id DESC LIMIT 20
");
$stmtLogs->execute([$war['id']]);
$logs = array_column($stmtLogs->fetchAll(), 'message');

$attFid = $war['attacker_faction_id'];
$defFid = $war['defender_faction_id'];

echo json_encode([
    'war_ended'       => false,
    'attacker_score'  => (int)$war['attacker_score'],
    'defender_score'  => (int)$war['defender_score'],
    'target_score'    => (int)$war['target_score'],
    'attacker_alive'  => (int)($counts[$attFid]['alive'] ?? 0),
    'attacker_dead'   => (int)($counts[$attFid]['dead']  ?? 0),
    'defender_alive'  => (int)($counts[$defFid]['alive'] ?? 0),
    'defender_dead'   => (int)($counts[$defFid]['dead']  ?? 0),
    'my_alive'        => $me ? (bool)$me['is_alive'] : false,
    'my_hp'           => $me ? (int)$me['current_hp'] : 0,
    'my_max_hp'       => $me ? (int)$me['max_hp'] : 100,
    'logs'            => $logs,
]);
