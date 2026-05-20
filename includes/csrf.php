<?php

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_input(): string
{
    return '<input type="hidden" name="_csrf" value="' . csrf_token() . '">';
}

function verify_csrf(): void
{
    if (!is_post()) return;

    $token      = $_SESSION['csrf'] ?? '';
    $submitted  = $_POST['_csrf'] ?? $_POST['csrf_token'] ?? '';

    if ($token === '' || !hash_equals($token, $submitted)) {
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        if ($isAjax || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        } else {
            http_response_code(403);
            redirect('login.php');
        }
        exit;
    }
}
