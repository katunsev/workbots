<?php

if (!isset($_ENV['DOTENV_LOADED'])) {
    if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
        require_once __DIR__ . '/../vendor/autoload.php';
    }
    if (class_exists('Dotenv\Dotenv')) {
        $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
        $dotenv->load();
        $_ENV['DOTENV_LOADED'] = 1;
    }
}

return [
    'telegram_bot_token' => $_ENV['TELEGRAM_BOT_TOKEN'] ?? '',
    'allowed_usernames' => explode(',', $_ENV['ALLOWED_USERNAMES'] ?? ''),
    'db' => [
        'dsn' => $_ENV['DB_DSN'] ?? '',
        'user' => $_ENV['DB_USER'] ?? '',
        'pass' => $_ENV['DB_PASS'] ?? '',
    ],
    'gitlab' => [
        'url' => ($_ENV['GITLAB_URL'] ?? '') . '/api/v4/',
        'token' => $_ENV['GITLAB_TOKEN'] ?? '',
        'project_id' => $_ENV['GITLAB_PROJECT_ID'] ?? '',
        'short_url' => $_ENV['GITLAB_URL'] ?? '',
    ],
    'rate_limiter' => [
        'storage_path' => __DIR__ . '/../storage/rate_limits',
        'limit' => 5,
        'seconds' => 60,
    ],
    'bots' => [
        'crm' => -1002673878204
    ],
];
