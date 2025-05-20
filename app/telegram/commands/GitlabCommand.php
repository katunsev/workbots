<?php
namespace App\Telegram\Commands;

use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Request;
use App\Services\AuthService;
use App\Services\RateLimiter;
use App\Services\GitLabService;

class GitlabCommand extends UserCommand
{
    protected $name = 'gitlab';
    protected $description = 'Работа с задачами GitLab';
    protected $usage = '/gitlab get|create ...';
    protected $version = '1.0.0';

    public function execute()
    {
        $message = $this->getMessage();
        $chat_id = $message->getChat()->getId();
        $username = $message->getFrom()->getUsername();

        $config = require __DIR__ . '/../../../config/config.php';
        $authService = new AuthService($config['allowed_usernames']);
        $rateLimiter = new RateLimiter(
            $config['rate_limiter']['storage_path'],
            $config['rate_limiter']['limit'],
            $config['rate_limiter']['seconds']
        );

        if ($rateLimiter->tooManyRequests($username)) {
            return Request::sendMessage([
                'chat_id' => $chat_id,
                'text' => 'Слишком много запросов. Попробуйте позже.',
            ]);
        }

        if (!$authService->authorize($username)) {
            return Request::sendMessage([
                'chat_id' => $chat_id,
                'text' => 'Доступ запрещен',
            ]);
        }

        $text = trim($message->getText(true));
        $parts = preg_split('/\s+/', $text);

        $gitlab = new GitLabService(
            $config['gitlab']['url'],
            $config['gitlab']['token'],
            $config['gitlab']['project_id']
        );

        if (!isset($parts[0])) {
            return Request::sendMessage([
                'chat_id' => $chat_id,
                'text' => "Использование:\n/gitlab get [filter]\n/gitlab create <title> [description]",
            ]);
        }

        if ($parts[0] === 'get') {
            // Пример: /gitlab get state=opened
            $filter = [];
            if (isset($parts[1])) {
                foreach (array_slice($parts, 1) as $f) {
                    if (strpos($f, '=') !== false) {
                        [$k, $v] = explode('=', $f, 2);
                        $filter[$k] = $v;
                    }
                }
            }
            $issues = $gitlab->getIssues($filter);
            if (empty($issues)) {
                $msg = "Задачи не найдены.";
            } else {
                $msg = "Задачи:\n";
                foreach (array_slice($issues, 0, 10) as $issue) {
                    $msg .= "#{$issue['iid']}: {$issue['title']} [{$issue['state']}]\n";
                }
            }
            return Request::sendMessage([
                'chat_id' => $chat_id,
                'text' => $msg,
            ]);
        }

        if ($parts[0] === 'create') {
            if (!isset($parts[1])) {
                return Request::sendMessage([
                    'chat_id' => $chat_id,
                    'text' => "Укажите название задачи.",
                ]);
            }
            $title = $parts[1];
            $description = isset($parts[2]) ? implode(' ', array_slice($parts, 2)) : null;
            $issue = $gitlab->createIssue($title, $description);
            return Request::sendMessage([
                'chat_id' => $chat_id,
                'text' => "Создана задача #{$issue['iid']}: {$issue['web_url']}",
            ]);
        }

        return Request::sendMessage([
            'chat_id' => $chat_id,
            'text' => "Неизвестная команда.",
        ]);
    }
}
