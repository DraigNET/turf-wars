<?php

$config = require __DIR__ . '/config.php';

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';

// Security headers
header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), camera=(), microphone=()');

start_session_secure($config);

if ($config['app']['maintenance'] && basename($_SERVER['PHP_SELF']) !== 'login.php') {
    $is_admin = false;
    if (isset($_SESSION['user_id'])) {
        $stmt = db()->prepare("SELECT is_admin FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $row  = $stmt->fetch();
        $is_admin = $row && $row['is_admin'];
    }
    if (!$is_admin) {
        http_response_code(503);
        echo '<!DOCTYPE html><html lang="en"><head><title>Maintenance</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="/assets/css/dashboard.css">
        </head><body class="text-white min-h-screen flex items-center justify-center">
        <div class="text-center">
            <img src="/assets/img/logo.png" style="height:160px;display:block;margin:0 auto 24px;">
            <h1 class="text-2xl font-bold mb-2">Under Maintenance</h1>
            <p class="text-gray-400">The game is currently down for maintenance. Check back soon.</p>
        </div></body></html>';
        exit;
    }
}
