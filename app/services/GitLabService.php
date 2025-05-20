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

    /**
     * Получить открытые задачи, у которых осталось менее 20% от оценки времени,
     * с учётом неучтённого сегодня времени (с 9:00 до сейчас или с момента последней смены метки).
     *
     * @param array $labels           Массив меток для поиска (логика ИЛИ).
     * @param array $additionalFilter Прочие параметры фильтрации.
     * @return array
     */
    public function getIssuesWithLowRemainingTimeIncludingToday(array $labels = [], array $additionalFilter = []): array
    {
        $filter       = array_merge([
            'state'    => 'opened',
            'per_page' => 100,
        ], $additionalFilter);

        $issues       = [];
        $seenIds      = [];
        $thresholdPct = 0.2; // 20%

        $tz         = new \DateTimeZone('Europe/Amsterdam');
        $now        = new \DateTime('now', $tz);
        $startOfDay = (clone $now)->setTime(9, 0, 0);
        $endOfDay   = (clone $now)->setTime(18, 0, 0);
        if ($now > $endOfDay) {
            $now = $endOfDay;
        }

        $labelSets = !empty($labels) ? $labels : [null];

        foreach ($labelSets as $label) {
            if ($label !== null) {
                $filter['labels'] = $label;
            } else {
                unset($filter['labels']);
            }

            $batch = $this->client->issues()->all($this->projectId, $filter);
            foreach ($batch as $issue) {
                $iid = $issue['iid'];
                if (isset($seenIds[$iid])) {
                    continue;
                }
                $seenIds[$iid] = true;

                $estimate = $issue['time_stats']['time_estimate']
                    ?? $issue['time_estimate']
                    ?? 0;
                if ($estimate <= 0) {
                    continue;
                }

                $spent = $issue['time_stats']['total_time_spent']
                    ?? $issue['total_time_spent']
                    ?? 0;

                // Определяем старт для учёта сегодня
                $start = $startOfDay;
                $events = $this->client->resourceLabelEvents()
                    ->all($this->projectId, $iid, ['per_page' => 100]);
                foreach ($events as $ev) {
                    $created = new \DateTime($ev['created_at'], $tz);
                    if ($created >= $startOfDay && $created <= $now && $created > $start) {
                        $start = $created;
                    }
                }

                // «Неучтённое» время сегодня
                $additional = max(0, $now->getTimestamp() - $start->getTimestamp());
                $totalSpent = $spent + $additional;

                $remaining = max(0, $estimate - $totalSpent);

                if ($remaining <= $estimate * $thresholdPct) {
                    // Форматируем оставшееся время в Xd Yh Zm
                    $days    = floor($remaining / 86400);
                    $hours   = floor(($remaining % 86400) / 3600);
                    $minutes = floor(($remaining % 3600) / 60);

                    $parts = [];
                    if ($days > 0) {
                        $parts[] = "{$days}d";
                    }
                    if ($hours > 0) {
                        $parts[] = "{$hours}h";
                    }
                    if ($minutes > 0) {
                        $parts[] = "{$minutes}m";
                    }
                    if (empty($parts)) {
                        $parts[] = "0m";
                    }
                    $issue['remaining_hours'] = implode(' ', $parts);

                    // Дополнительно оставляем информацию о «неучтённом» сегодня
                    $issue['additional_today_hours'] = round($additional / 3600, 2);

                    $issues[] = $issue;
                }
            }
        }

        return $issues;
    }

    /**
     * Получить открытые просроченные задачи,
     * с учётом неучтённого сегодня времени (с 9:00 до сейчас или с момента последней смены метки).
     *
     * @param array $labels           Массив меток для поиска (логика ИЛИ).
     * @param array $additionalFilter Прочие параметры фильтрации.
     * @return array
     */
    public function getOverdueIssuesIncludingToday(array $labels = [], array $additionalFilter = []): array
    {
        $filter       = array_merge([
            'state'    => 'opened',
            'per_page' => 100,
        ], $additionalFilter);

        $issues       = [];
        $seenIds      = [];

        // Таймзона, начало/конец рабочего дня
        $tz         = new \DateTimeZone('Europe/Amsterdam');
        $now        = new \DateTime('now', $tz);
        $startOfDay = (clone $now)->setTime(9, 0, 0);
        $endOfDay   = (clone $now)->setTime(18, 0, 0);
        if ($now > $endOfDay) {
            $now = $endOfDay;
        }

        // Если метки не заданы, обрабатываем один раз без фильтра по labels
        $labelSets = !empty($labels) ? $labels : [null];

        foreach ($labelSets as $label) {
            if ($label !== null) {
                $filter['labels'] = $label;
            } else {
                unset($filter['labels']);
            }

            $batch = $this->client->issues()->all($this->projectId, $filter);
            foreach ($batch as $issue) {
                $iid = $issue['iid'];
                if (isset($seenIds[$iid])) {
                    continue;
                }
                $seenIds[$iid] = true;

                // 1) Оценка
                $estimate = $issue['time_stats']['time_estimate']
                    ?? $issue['time_estimate']
                    ?? 0;
                if ($estimate <= 0) {
                    continue;
                }

                // 2) Уже учтённое
                $spent = $issue['time_stats']['total_time_spent']
                    ?? $issue['total_time_spent']
                    ?? 0;

                // 3) Определяем старт «с учётом меток»
                $start = $startOfDay;
                $events = $this->client->resourceLabelEvents()
                    ->all($this->projectId, $iid, ['per_page' => 100]);
                foreach ($events as $ev) {
                    $created = new \DateTime($ev['created_at'], $tz);
                    if ($created >= $startOfDay && $created <= $now && $created > $start) {
                        $start = $created;
                    }
                }

                // 4) «Неучтённое» сегодня
                $additional = max(0, $now->getTimestamp() - $start->getTimestamp());
                $totalSpent = $spent + $additional;

                // 5) Проверяем просрочку: потрачено больше оценки
                if ($totalSpent > $estimate) {
                    $overdueSec = $totalSpent - $estimate;

                    // Форматируем в Xd Yh Zm
                    $days    = floor($overdueSec / 86400);
                    $hours   = floor(($overdueSec % 86400) / 3600);
                    $minutes = floor(($overdueSec % 3600) / 60);

                    $parts = [];
                    if ($days > 0)    $parts[] = "{$days}d";
                    if ($hours > 0)   $parts[] = "{$hours}h";
                    if ($minutes > 0) $parts[] = "{$minutes}m";
                    if (empty($parts)) {
                        $parts[] = "0m";
                    }

                    $issue['overdue_time']           = implode(' ', $parts);
                    $issue['additional_today_hours'] = round($additional / 3600, 2);

                    $issues[] = $issue;
                }
            }
        }

        return $issues;
    }




    public function getUsers(array $filter = []): array
    {
        return $this->client->users()->all($filter);
    }
}
