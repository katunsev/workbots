<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Longman\TelegramBot\Telegram;

// Загрузка .env
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$config = require __DIR__ . '/../config/config.php';

// Укажите имя своего бота (как зарегистрировали в BotFather)
$bot_name = 'MyBot';

// Используем php-telegram-bot/core
$telegram = new Telegram($config['telegram_bot_token'], $bot_name);

// Добавляем путь с командами
$telegram->addCommandsPath(__DIR__ . '/../app/Telegram/Commands');

try {
    $telegram->handle();
} catch (Longman\TelegramBot\Exception\TelegramException $e) {
    // логирование ошибок
    error_log($e->getMessage());
}
