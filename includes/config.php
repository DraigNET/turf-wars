<?php

// Prefer environment variables for credentials (set via Apache SetEnv or .env loaded at server level).
// Never commit real credentials to version control.
// Database user should NOT be root — create a restricted user with only SELECT/INSERT/UPDATE/DELETE on this DB.

return [
    'app' => [
        'name'         => 'The Streets: Turf Wars',
        'base_url'     => getenv('APP_BASE_URL')     ?: 'http://turfwars.YOURDOMAIN.com',
        'session_name' => getenv('APP_SESSION_NAME') ?: 'stw_session',
        'maintenance'  => false,
    ],

    'db' => [
        'host'     => getenv('DB_HOST')     ?: '127.0.0.1',
        'dbname'   => getenv('DB_NAME')     ?: 'turf_wars',
        'username' => getenv('DB_USER')     ?: 'db_user',
        'password' => getenv('DB_PASS')     ?: '',
        'charset'  => 'utf8mb4',
    ],
];
