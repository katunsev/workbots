<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../vendor/autoload.php';

$config = require __DIR__ . '/../config/config.php';
$usermap = require __DIR__ . '/../config/usersmap.php';

use App\services\GitLabService;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Telegram;

$gitlab = new GitLabService(
    $config['gitlab']['url'],
    $config['gitlab']['token'],
    $config['gitlab']['project_id']
);

$gitlabUsers = $gitlab->getUsers(['active' => true, 'search' => 'dkatunsev']);
$users = [];

foreach ($gitlabUsers as $gitlabUser) {
    if ($usermap[$gitlabUser['id']]) {
        $user = $usermap[$gitlabUser['id']];
        $user['username'] = $gitlabUser['username'];
        $user['name'] = $gitlabUser['name'];
        $users[] = $user;
    }
}

$issues = $gitlab->getOpenedIssuesWithoutEstimateOrLabels(['Status::В разработке', 'Status::На проверку', 'Status::Тестируется'],
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
        $link = $config['gitlab']['short_url'] . '/root/getcourse/-/issues/' . $issue['iid'];
        $titles .= "[{$link}]({$title}), " . "{$assigneeStr}" . PHP_EOL;
    }
}

$bot_name = 'dkassisbot';
$telegram = new Telegram($config['telegram_bot_token'], $bot_name);
$telegram->addCommandsPath(__DIR__ . '/../app/Telegram/Commands');

if ($titles) {
    $result = Request::sendMessage([
        'chat_id' => $usermap[31]['chat_id'],
        'text'    => 'Неоцененные задачи: ' . PHP_EOL . $titles,
        'parse_mode' => 'MARKDOWN'
    ]);
}