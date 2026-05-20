<?php
require_once 'includes/bootstrap.php';
require_login();
header('Content-Type: application/json');

if (
    !isset($_POST['_csrf']) ||
    !hash_equals($_SESSION['csrf'] ?? '', $_POST['_csrf'])
) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid CSRF'
    ]);
    exit;
}

$pdo = db();

/**
 * Get character
 */
$stmt = $pdo->prepare("
    SELECT id, turf_id, last_action, food
    FROM characters
    WHERE user_id = ? AND city_id = ?
    LIMIT 1
");
$stmt->execute([user_id(), current_city_id()]);
$char = $stmt->fetch();

if (!$char) {
    game_log('move', 'fail', 'No character found');
    echo json_encode(['success' => false, 'error' => 'No character found']);
    exit;
}

/**
 * Validate turf_id
 */
$turf_id = (int)($_POST['turf_id'] ?? 0);

if ($turf_id <= 0) {
    game_log('move', 'fail', 'Invalid turf');
    echo json_encode(['success' => false, 'error' => 'Invalid turf']);
    exit;
}

/**
 * Prevent moving to same turf
 */
if ($turf_id == $char['turf_id']) {
    game_log('move', 'blocked', 'Same turf', [
        'turf_id' => $turf_id
    ]);
    echo json_encode(['success' => false, 'error' => 'You are already here']);
    exit;
}

/**
 * Check turf exists
 */
$stmt = $pdo->prepare("SELECT id, name FROM turfs WHERE id = ? AND city_id = ?");
$stmt->execute([$turf_id, current_city_id()]);
$turf = $stmt->fetch();

if (!$turf) {
    game_log('move', 'fail', 'Turf not found', [
        'turf_id' => $turf_id
    ]);
    echo json_encode(['success' => false, 'error' => 'Turf not found']);
    exit;
}

/**
 * Movement cooldown
 */
$now = time();
$lastAction = $char['last_action'] ? strtotime($char['last_action']) : 0;

if ($lastAction > 0 && ($now - $lastAction) < 2) {
    game_log('move', 'blocked', 'Cooldown active', [
        'turf_id' => $turf_id
    ]);
    echo json_encode(['success' => false, 'error' => 'Slow down']);
    exit;
}

/**
 * Update player location
 */
$stmt = $pdo->prepare("
    UPDATE characters 
    SET turf_id = ?, 
        last_action = ?,
        food = GREATEST(food - 2, 0)
    WHERE id = ?
");
$stmt->execute([$turf_id, $now, $char['id']]);

/**
 * LOG SUCCESS
 */
game_log('move', 'success', 'Moved turf', [
    'from' => $char['turf_id'],
    'to' => $turf_id,
    'turf_id' => $turf_id
]);

/**
 * Get dominant faction
 */
$stmt = $pdo->prepare("
    SELECT f.name, tc.control_percent
    FROM turf_control tc
    JOIN factions f ON f.id = tc.faction_id
    WHERE tc.turf_id = ?
    ORDER BY tc.control_percent DESC
    LIMIT 1
");
$stmt->execute([$turf_id]);
$dominant = $stmt->fetch();

/**
 * Get turf features
 */
$stmt = $pdo->prepare("
    SELECT feature_type 
    FROM turf_features 
    WHERE turf_id = ?
");
$stmt->execute([$turf_id]);
$features = $stmt->fetchAll(PDO::FETCH_COLUMN);

/**
 * Response
 */
echo json_encode([
    'success' => true,
    'turf' => [
        'id' => (int)$turf['id'],
        'name' => $turf['name'],
        'dominant' => $dominant['name'] ?? 'None',
        'percent' => isset($dominant['control_percent']) 
            ? (int)$dominant['control_percent'] 
            : 0
    ],
    'features' => $features,
    'player' => [
        'food' => max($char['food'] - 2, 0)
    ]
]);
exit;