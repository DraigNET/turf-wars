<?php

function game_log($action, $result, $message = '', $details = [])
{
    try {
        $pdo = db();

        $userId = $_SESSION['user_id'] ?? null;

        $characterId = null;
        if ($userId) {
            $stmt = $pdo->prepare("SELECT id FROM characters WHERE user_id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $row = $stmt->fetch();
            if ($row) {
                $characterId = $row['id'];
            }
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $stmt = $pdo->prepare("
            INSERT INTO game_logs 
            (user_id, character_id, action_type, status, message, turf_id, ip_address, user_agent, context)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $userId,
            $characterId,
            $action,
            $result,
            $message,
            $details['turf_id'] ?? null,
            $ip,
            $ua,
            json_encode($details)
        ]);

    } catch (Throwable $e) {
        // DO NOT BREAK OUTPUT
        error_log("[GAME_LOG_FAIL] " . $e->getMessage());
    }
}