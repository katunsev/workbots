<?php
namespace App\Services;

use Gitlab\Client;

class GitLabService extends BaseService
{
    protected Client $client;
    protected string $projectId;

    public function __construct(string $url, string $token, string $projectId)
    {
        $this->client = Client::create($url);
        $this->client->authenticate($token, Client::AUTH_HTTP_TOKEN);
        $this->projectId = $projectId;
    }

    /**
     * Получить задачи по фильтру
     * @param array $filter
     * @return array
     */
    public function getIssues(array $filter = []): array
    {
        // Для версии 11.x:
        return $this->client->issues()->all($this->projectId, $filter);
    }

    /**
     * Создать задачу
     * @param string $title
     * @param string|null $description
     * @param array $params
     * @return array
     */
    public function createIssue(string $title, ?string $description = null, array $params = []): array
    {
        $data = array_merge(
            ['title' => $title],
            $description ? ['description' => $description] : [],
            $params
        );
        return $this->client->issues()->create($this->projectId, $data);
    }
}
