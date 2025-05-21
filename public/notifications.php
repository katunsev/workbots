<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../vendor/autoload.php';

$config = require __DIR__ . '/../config/config.php';

use Longman\TelegramBot\Request;
use Longman\TelegramBot\Telegram;

$message = $argc[1] ?? null;

if (!$message) {
    echo 'Сообщение не задано';
    return;
}

$bot_name = 'dkassisbot';
$telegram = new Telegram($config['telegram_bot_token'], $bot_name);
$telegram->addCommandsPath(__DIR__ . '/../app/Telegram/Commands');

$result = Request::sendMessage([
    'chat_id' => $config['bots']['crm'],
    'text'    => "{$message}: ",
    'parse_mode' => 'MARKDOWN'
]);