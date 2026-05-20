<?php

function redirect($url)
{
    header("Location: $url");
    exit;
}

function e($str)
{
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function is_post()
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function decay_turf_heat($pdo, $turfId)
{
    $stmt = $pdo->prepare("SELECT heat, heat_updated_at FROM turfs WHERE id = ?");
    $stmt->execute([$turfId]);
    $row = $stmt->fetch();

    if (!$row || (int)$row['heat'] <= 0) return;

    $decayRate    = 180; // 1 point per 3 minutes
    $secondsPassed = time() - strtotime($row['heat_updated_at']);
    $decay         = (int)floor($secondsPassed / $decayRate);

    if ($decay <= 0) return;

    // Advance timestamp by exactly the seconds consumed to avoid drift
    $pdo->prepare("
        UPDATE turfs
        SET heat             = GREATEST(heat - ?, 0),
            heat_updated_at  = DATE_ADD(heat_updated_at, INTERVAL ? SECOND)
        WHERE id = ?
    ")->execute([$decay, $decay * $decayRate, $turfId]);
}

function current_city_id(): int
{
    return 1;
}

function get_character()
{
    $pdo    = db();
    $cityId = current_city_id();

    $stmt = $pdo->prepare("
        SELECT c.*, f.name AS faction_name, f.color, f.icon_path,
               w.name AS weapon_name, w.war_points_bonus, w.kill_chance_bonus, w.damage AS weapon_damage
        FROM characters c
        JOIN factions f ON f.id = c.faction_id
        LEFT JOIN weapons w ON w.id = c.weapon_id
        WHERE c.user_id = ? AND c.city_id = ?
        LIMIT 1
    ");

    $stmt->execute([user_id(), $cityId]);
    return $stmt->fetch();
}