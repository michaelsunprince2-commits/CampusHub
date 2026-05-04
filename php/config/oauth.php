<?php

require_once __DIR__ . '/env.php';

/**
 * OAuth provider credentials.
 *
 * Keep real credentials out of this file. Put local values in your server
 * environment or an untracked .env file, then read them with getenv().
 */

return [
    'google' => [
        'client_id' => trim(getenv('GOOGLE_CLIENT_ID') ?: ''),
        'client_secret' => trim(getenv('GOOGLE_CLIENT_SECRET') ?: ''),
    ],
    'apple' => [
        'client_id' => trim(getenv('APPLE_CLIENT_ID') ?: ''),
        'client_secret' => trim(getenv('APPLE_CLIENT_SECRET') ?: ''),
    ],
    'github' => [
        'client_id' => trim(getenv('GITHUB_CLIENT_ID') ?: ''),
        'client_secret' => trim(getenv('GITHUB_CLIENT_SECRET') ?: ''),
    ],
];
