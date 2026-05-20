<?php

function user_id()
{
    return $_SESSION['user_id'] ?? null;
}

function logged_in()
{
    return user_id() !== null;
}

function login($id)
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $id;
}

function logout()
{
    session_destroy();
}

function require_login()
{
    if (!logged_in()) {
        redirect('login.php');
    }
}

function require_guest()
{
    if (logged_in()) {
        redirect('dashboard.php');
    }
}