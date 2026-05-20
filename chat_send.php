<?php
require_once 'includes/bootstrap.php';
require_login();
header('Content-Type: application/json');

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF']);
    exit;
}

$pdo = db();

$userId = user_id();
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

// Get character for current city
$stmt = $pdo->prepare("SELECT * FROM characters WHERE user_id = ? AND city_id = ? LIMIT 1");
$stmt->execute([$userId, current_city_id()]);
$char = $stmt->fetch();

if (!$char) {
    exit;
}

// Update latest known IP
$stmt = $pdo->prepare("
    UPDATE users
    SET last_ip = ?
    WHERE id = ?
");
$stmt->execute([$ip, $userId]);

// Check if user is muted
$stmt = $pdo->prepare("
    SELECT *
    FROM chat_mutes
    WHERE user_id = ?
      AND (
            expires_at IS NULL
            OR expires_at > NOW()
      )
    ORDER BY created_at DESC
    LIMIT 1
");
$stmt->execute([$userId]);
$mute = $stmt->fetch();

if ($mute) {
    http_response_code(403);

    $reason = trim((string)($mute['reason'] ?? ''));
    $msg = 'You are muted from chat.';

    if ($reason !== '') {
        $msg .= ' Reason: ' . $reason;
    }

    exit($msg);
}

$message = trim($_POST['message'] ?? '');
$channel = $_POST['channel'] ?? 'global';

if (!in_array($channel, ['global', 'faction', 'zone'], true)) {
    $channel = 'global';
}

if ($message === '' || strlen($message) > 200) exit;

$turf_id = null;
$faction_id = null;

if ($channel === 'zone') {
    $turf_id = $char['turf_id'];
} elseif ($channel === 'faction') {
    $faction_id = $char['faction_id'];
}

$stmt = $pdo->prepare("
    INSERT INTO chat_messages
    (character_id, character_name, message, channel, turf_id, faction_id, city_id, ip_address)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->execute([
    $char['id'],
    $char['name'],
    $message,
    $channel,
    $turf_id,
    $faction_id,
    current_city_id(),
    $ip
]);