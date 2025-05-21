<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../vendor/autoload.php';

$config = require __DIR__ . '/../config/config.php';

use App\services\GitLabService;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Telegram;

$gitlab = new GitLabService(
    $config['gitlab']['url'],
    $config['gitlab']['token'],
    $config['gitlab']['project_id']
);

$function = '';
$message = '';
$type = $_GET['type'] ?? null;

if (!$type) {
    echo 'Тип операции не задан';
    return;
}

$param = null;

switch ($type) {
    case 'expired': {
        $function = 'getOverdueIssuesIncludingToday';
        $message = 'Просроченные задачи';
        $chatId = 207223883;
        $param = 'overdue_time';
        break;
    }
    case 'expired-soon': {
        $function = 'getIssuesWithLowRemainingTimeIncludingToday';
        $message = 'Скоро просрочатся задачи';
        $chatId = 207223883;
        $param = 'remaining_hours';
        break;
    }
    case 'notestimate': {
        $function = 'getOpenedIssuesWithoutEstimateOrLabels';
        $message = 'Неоцененные задачи';
        $chatId = $config['bots']['crm'];
        break;
    }
}

if (!$function) {
    echo "Операция для {$type} не существует";
    return;
}

$issues = $gitlab->$function(['Status::В разработке', 'Status::На проверку', 'Status::Тестируется'],
    [
        'milestone' => 'CRM Q2',
        'state' => 'opened',
    ]
);

$titles = '';
if (empty($issues)) {
    echo "Нет задач\n";
} else {
    foreach ($issues as $issue) {
        $assignees = $issue['assignees'];
        $assigneeStr = '';
        foreach ($assignees as $assignee) {
            $assigneeStr .= $assignee['username'] . ', ';
        }
        $title = $issue['title'];
        $link =  $config['gitlab']['short_url'] . '/root/getcourse/-/issues/' . $issue['iid'];
        $titles .= ($param ? $issue[$param] : null) . " [{$link}]({$title}), " . "{$assigneeStr}" . PHP_EOL;
    }
}

$bot_name = 'dkassisbot';
$telegram = new Telegram($config['telegram_bot_token'], $bot_name);
$telegram->addCommandsPath(__DIR__ . '/../app/Telegram/Commands');

if ($titles) {
    $result = Request::sendMessage([
        'chat_id' => $chatId,
        'text'    => "{$message}: " . PHP_EOL . $titles,
        'parse_mode' => 'MARKDOWN'
    ]);
}