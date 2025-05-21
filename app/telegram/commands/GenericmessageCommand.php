<?php

namespace App\telegram\commands;

use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Request;

/**
 * Класс для эхо-ответа на любое сообщение.
 */
class GenericmessageCommand extends UserCommand
{
    protected $name = 'genericmessage';
    protected $description = 'Эхо-бот: повторяет любые сообщения';
    protected $usage = '/genericmessage';
    protected $version = '1.0.0';

    public function execute(): ServerResponse
    {
        $message = $this->getMessage();
        $chat_id = $message->getChat()->getId();
        $text    = $message->getText(true); // true — без команды (если была)

        // Просто повторяем полученный текст
        return Request::sendMessage([
            'chat_id' => $chat_id,
            'text'    => $message->getText() ?? '',
        ]);
    }
}
