<?php

function start_session_secure($config)
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $lifetime = 30 * 24 * 60 * 60; // 30 days

    session_name($config['app']['session_name']);

    ini_set('session.gc_maxlifetime', $lifetime);

    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();

    if (!isset($_SESSION['init'])) {
        session_regenerate_id(true);
        $_SESSION['init'] = true;
    }
}