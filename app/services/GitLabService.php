<?php
namespace App\services;

use Gitlab\Client;

class GitLabService extends BaseService
{
    protected Client $client;
    protected string $projectId;

    public function __construct(string $url, string $token, string $projectId)
    {
        $this->client = new Client();
        $apiUrl = rtrim($url, '/') . '/api/v4/';
        $this->client->setUrl($apiUrl);
        $this->client->authenticate($token, Client::AUTH_HTTP_TOKEN);
        $this->projectId = $projectId;
    }

    public function getIssues(array $filter = []): array
    {
        return $this->client->issues()->all($this->projectId, $filter);
    }

    public function createIssue(string $title, ?string $description = null, array $params = []): array
    {
        $data = array_merge(
            ['title' => $title],
            $description ? ['description' => $description] : [],
            $params
        );
        return $this->client->issues()->create($this->projectId, $data);
    }

    public function getOpenedIssuesWithoutEstimate(array $additionalFilter = []): array
    {
        $filter = array_merge(['state' => 'opened'], $additionalFilter);
        $filter['per_page'] = 100;

        $issues = $this->client->issues()->all($this->projectId, $filter);

        $noEstimate = [];
        foreach ($issues as $issue) {
            $estimate = 0;
            if (isset($issue['time_stats']['time_estimate'])) {
                $estimate = $issue['time_stats']['time_estimate'];
            } elseif (isset($issue['time_estimate'])) {
                $estimate = $issue['time_estimate'];
            }
            if ($estimate == 0) {
                $noEstimate[] = $issue;
            }
        }
        return $noEstimate;
    }

    /**
     * Получить открытые задачи без оценки, у которых есть хотя бы одна из указанных меток (ИЛИ).
     * @param array $labels Массив меток для поиска с логикой ИЛИ.
     * @param array $additionalFilter Прочие параметры фильтрации.
     * @return array
     */
    public function getOpenedIssuesWithoutEstimateOrLabels(array $labels = [], array $additionalFilter = []): array
    {
        $filter = array_merge(['state' => 'opened', 'per_page' => 100], $additionalFilter);

        $issues = [];
        $seen_ids = [];

        foreach ($labels as $label) {
            $filter['labels'] = $label;
            $batch = $this->client->issues()->all($this->projectId, $filter);

            foreach ($batch as $issue) {
                // Удаляем дубли (по id)
                if (isset($seen_ids[$issue['id']])) {
                    continue;
                }

                // Проверяем оценку
                $estimate = 0;
                if (isset($issue['time_stats']['time_estimate'])) {
                    $estimate = $issue['time_stats']['time_estimate'];
                } elseif (isset($issue['time_estimate'])) {
                    $estimate = $issue['time_estimate'];
                }

                if ($estimate == 0) {
                    $issues[] = $issue;
                    $seen_ids[$issue['id']] = true;
                }
            }
        }

        return $issues;
    }

    /**
     * Получить открытые задачи без оценки по меткам (ИЛИ), у которых потраченное время >= оценочного.
     *
     * @param array $labels Массив меток для поиска (ИЛИ). Можно пустой.
     * @param array $additionalFilter Прочие параметры фильтрации.
     * @return array
     */
    public function getOverdueIssuesFromUnestimatedOrLabeled(array $labels = [], array $additionalFilter = []): array
    {
        $filter = array_merge(['state' => 'opened', 'per_page' => 100], $additionalFilter);

        $result = [];

        foreach ($labels as $label) {
            $filter['labels'] = $label;
            $batch = $this->client->issues()->all($this->projectId, $filter);

            foreach ($batch as $issue) {
                $estimate = 0;
                $spent = 0;
                if (isset($issue['time_stats']['time_estimate'])) {
                    $estimate = $issue['time_stats']['time_estimate'];
                } elseif (isset($issue['time_estimate'])) {
                    $estimate = $issue['time_estimate'];
                }
                if (isset($issue['time_stats']['total_time_spent'])) {
                    $spent = $issue['time_stats']['total_time_spent'];
                } elseif (isset($issue['total_time_spent'])) {
                    $spent = $issue['total_time_spent'];
                }

                if ($spent >= $estimate) {
                    $issue['expired'] = ($spent - $estimate) / 3600;
                    $result[] = $issue;
                }
            }
        }

        return $result;
    }



    public function getUsers(array $filter = []): array
    {
        return $this->client->users()->all($filter);
    }
}
