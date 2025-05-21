<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Longman\TelegramBot\Telegram;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$config = require __DIR__ . '/../config/config.php';

$bot_name = 'MyBot';
$telegram = new Telegram($config['telegram_bot_token'], $bot_name);
$telegram->addCommandsPath(__DIR__ . '/../app/Telegram/Commands');

// Запускаем стандартную обработку
$telegram->handle();
