<?php

return [
    'telegram_bot_token' => getenv('TELEGRAM_BOT_TOKEN'),
    'allowed_usernames' => explode(',', getenv('ALLOWED_USERNAMES')),
    'db' => [
        'dsn' => getenv('DB_DSN'),
        'user' => getenv('DB_USER'),
        'pass' => getenv('DB_PASS'),
    ],
    'gitlab' => [
        'url' => getenv('GITLAB_URL'),
        'token' => getenv('GITLAB_TOKEN'),
        'project_id' => getenv('GITLAB_PROJECT_ID'),
    ],
    'rate_limiter' => [
        'storage_path' => __DIR__ . '/../storage/rate_limits',
        'limit' => 5,
        'seconds' => 60,
    ]
];
