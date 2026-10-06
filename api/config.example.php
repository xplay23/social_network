<?php

declare(strict_types=1);

return [
    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'pg629141_social',
        'user' => 'circle_app',
        'password' => 'change_me',
    ],
    'jwt_secret' => 'replace_this_with_at_least_32_random_characters',
    'client_origins' => ['http://localhost:5173'],
];
