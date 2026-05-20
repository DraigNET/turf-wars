<?php
require_once 'includes/bootstrap.php';
require_login();
header('Content-Type: application/json');

$pdo = db();

/**
 * Get current character for active city
 */
$stmt = $pdo->prepare("SELECT * FROM characters WHERE user_id = ? AND city_id = ? LIMIT 1");
$stmt->execute([user_id(), current_city_id()]);
$char = $stmt->fetch();

if (!$char) {
    echo json_encode(['messages' => []]);
    exit;
}

$channel = $_GET['channel'] ?? 'global';

if (!in_array($channel, ['global', 'faction', 'zone'], true)) {
    $channel = 'global';
}

$lastId = (int)($_GET['last_id'] ?? 0);

$query = "";
$params = [];

/**
 * GLOBAL CHAT
 */
if ($channel === 'global') {

    $query = "
        SELECT 
            cm.id,
            cm.character_id,
            cm.character_name AS name,
            cm.message,
            f.color,
            f.icon_path
        FROM chat_messages cm
        JOIN characters c ON c.id = cm.character_id
        JOIN factions f ON f.id = c.faction_id
        WHERE cm.channel = 'global'
        AND cm.city_id = ?
        AND cm.id > ?
        ORDER BY cm.id ASC
        LIMIT 50
    ";

    $params = [current_city_id(), $lastId];

/**
 * FACTION CHAT
 */
} elseif ($channel === 'faction') {

    $query = "
        SELECT 
            cm.id,
            cm.character_id,
            cm.character_name AS name,
            cm.message,
            f.color,
            f.icon_path
        FROM chat_messages cm
        JOIN characters c ON c.id = cm.character_id
        JOIN factions f ON f.id = c.faction_id
        WHERE cm.channel = 'faction'
        AND cm.city_id = ?
        AND cm.faction_id = ?
        AND cm.id > ?
        ORDER BY cm.id ASC
        LIMIT 50
    ";

    $params = [current_city_id(), $char['faction_id'], $lastId];

/**
 * ZONE CHAT
 */
} else {

    $query = "
        SELECT 
            cm.id,
            cm.character_id,
            cm.character_name AS name,
            cm.message,
            f.color,
            f.icon_path
        FROM chat_messages cm
        JOIN characters c ON c.id = cm.character_id
        JOIN factions f ON f.id = c.faction_id
        WHERE cm.channel = 'zone'
        AND cm.city_id = ?
        AND cm.turf_id = ?
        AND cm.id > ?
        ORDER BY cm.id ASC
        LIMIT 50
    ";

    $params = [current_city_id(), $char['turf_id'], $lastId];
}

/**
 * Execute query
 */
$stmt = $pdo->prepare($query);
$stmt->execute($params);

/**
 * Output JSON
 */
echo json_encode([
    'messages' => $stmt->fetchAll(PDO::FETCH_ASSOC)
]);