<?php
declare(strict_types=1);

namespace App\services;

use Gitlab\Client;

/**
 * Сервис для работы с задачами GitLab.
 */
class GitLabService extends BaseService
{
    protected Client $client;
    protected string $projectId;

    /**
     * GitLabService constructor.
     *
     * @param string $url       URL GitLab
     * @param string $token     Токен доступа
     * @param string $projectId ID проекта
     */
    public function __construct(string $url, string $token, string $projectId)
    {
        $this->client = new Client();
        $this->client->setUrl(rtrim($url, '/') . '/api/v4/');
        $this->client->authenticate($token, Client::AUTH_HTTP_TOKEN);
        $this->projectId = $projectId;
    }

    /**
     * Получить задачи по фильтру.
     *
     * @param array $filter
     * @return array
     */
    public function getIssues(array $filter = []): array
    {
        return $this->client->issues()->all($this->projectId, $filter);
    }

    /**
     * Создать задачу.
     *
     * @param string      $title
     * @param string|null $description
     * @param array       $params
     * @return array
     */
    public function createIssue(string $title, ?string $description = null, array $params = []): array
    {
        $data = array_merge(['title' => $title], $description ? ['description' => $description] : [], $params);
        return $this->client->issues()->create($this->projectId, $data);
    }

    /**
     * Получить открытые задачи без оценки времени.
     *
     * @param array $additionalFilter
     * @return array
     */
    public function getOpenedIssuesWithoutEstimate(array $additionalFilter = []): array
    {
        $filter = array_merge(['state' => 'opened', 'per_page' => 100], $additionalFilter);
        $issues = $this->getIssues($filter);

        return array_values(array_filter($issues, fn(array $issue): bool => $this->getTimeEstimate($issue) === 0));
    }

    /**
     * Получить открытые задачи без оценки, с одной из указанных меток (логика ИЛИ).
     *
     * @param array $labels
     * @param array $additionalFilter
     * @return array
     */
    public function getOpenedIssuesWithoutEstimateOrLabels(array $labels = [], array $additionalFilter = []): array
    {
        $filter = array_merge(['state' => 'opened', 'per_page' => 100], $additionalFilter);
        $issues = [];
        $seen = [];

        foreach ($labels as $label) {
            $filter['labels'] = $label;
            foreach ($this->getIssues($filter) as $issue) {
                if (!isset($seen[$issue['id']]) && $this->getTimeEstimate($issue) === 0) {
                    $issues[] = $issue;
                    $seen[$issue['id']] = true;
                }
            }
        }
        return $issues;
    }

    /**
     * Получить открытые задачи, у которых осталось менее 20% времени по оценке (с учетом "неучтённого" времени сегодня).
     *
     * @param array $labels
     * @param array $additionalFilter
     * @return array
     */
    public function getIssuesWithLowRemainingTimeIncludingToday(array $labels = [], array $additionalFilter = []): array
    {
        return $this->getFilteredIssuesByTime(
            $labels,
            $additionalFilter,
            function(int $estimate, int $totalSpent): bool {
                return $estimate > 0 && $totalSpent <= $estimate && $estimate - $totalSpent <= $estimate * 0.2;
            },
            function(array $issue, int $remaining, int $additional): array {
                $issue['remaining_hours'] = $this->formatTime($remaining);
                $issue['additional_today_hours'] = round($additional / 3600, 2);
                return $issue;
            }
        );
    }

    /**
     * Получить открытые просроченные задачи (с учетом "неучтённого" времени сегодня).
     *
     * @param array $labels
     * @param array $additionalFilter
     * @return array
     */
    public function getOverdueIssuesIncludingToday(array $labels = [], array $additionalFilter = []): array
    {
        return $this->getFilteredIssuesByTime(
            $labels,
            $additionalFilter,
            function(int $estimate, int $totalSpent): bool {
                return $estimate > 0 && $totalSpent > $estimate;
            },
            function(array $issue, int $overdue, int $additional): array {
                $issue['overdue_time'] = $this->formatTime($overdue);
                $issue['additional_today_hours'] = round($additional / 3600, 2);
                return $issue;
            }
        );
    }

    /**
     * Получить пользователей по фильтру.
     *
     * @param array $filter
     * @return array
     */
    public function getUsers(array $filter = []): array
    {
        return $this->client->users()->all($filter);
    }

    // ================== PRIVATE HELPERS ==================

    /**
     * Получить оценку времени из задачи.
     *
     * @param array $issue
     * @return int
     */
    private function getTimeEstimate(array $issue): int
    {
        return (int)($issue['time_stats']['time_estimate'] ?? $issue['time_estimate'] ?? 0);
    }

    /**
     * Получить потраченное время из задачи.
     *
     * @param array $issue
     * @return int
     */
    private function getTimeSpent(array $issue): int
    {
        return (int)($issue['time_stats']['total_time_spent'] ?? $issue['total_time_spent'] ?? 0);
    }

    /**
     * Универсальный фильтр задач по времени.
     *
     * @param array    $labels
     * @param array    $additionalFilter
     * @param callable $timeFilter function(int $estimate, int $totalSpent): bool
     * @param callable $formatter  function(array $issue, int $diff, int $additional): array
     * @return array
     */
    private function getFilteredIssuesByTime(
        array $labels,
        array $additionalFilter,
        callable $timeFilter,
        callable $formatter
    ): array
    {
        $filter = array_merge(['state' => 'opened', 'per_page' => 100], $additionalFilter);
        $tz = new \DateTimeZone('Europe/Amsterdam');
        $now = new \DateTime('now', $tz);
        $startOfDay = (clone $now)->setTime(9, 0, 0);
        $endOfDay = (clone $now)->setTime(18, 0, 0);
        if ($now > $endOfDay) $now = $endOfDay;
        $labelSets = !empty($labels) ? $labels : [null];
        $issues = [];
        $seen = [];
        foreach ($labelSets as $label) {
            if ($label !== null) $filter['labels'] = $label; else unset($filter['labels']);
            foreach ($this->getIssues($filter) as $issue) {
                $iid = $issue['iid'];
                if (isset($seen[$iid])) continue;
                $seen[$iid] = true;
                $estimate = $this->getTimeEstimate($issue);
                if ($estimate <= 0) continue;
                $spent = $this->getTimeSpent($issue);
                $start = $this->getLastLabelEventTime($iid, $startOfDay, $now, $tz) ?? $startOfDay;
                $additional = max(0, $now->getTimestamp() - $start->getTimestamp());
                $totalSpent = $spent + $additional;
                $remaining = max(0, $estimate - $totalSpent);
                $overdue = $totalSpent - $estimate;
                // В зависимости от фильтра либо оставшееся, либо просрочка
                $diff = $timeFilter === $this->getLowRemainingTimeFilter() ? $remaining : $overdue;
                if ($timeFilter($estimate, $totalSpent)) {
                    $issues[] = $formatter($issue, $diff, $additional);
                }
            }
        }
        return $issues;
    }

    /**
     * Получить время последней смены метки за сегодня.
     *
     * @param int             $iid
     * @param \DateTime       $startOfDay
     * @param \DateTime       $now
     * @param \DateTimeZone   $tz
     * @return \DateTime|null
     */
    private function getLastLabelEventTime(int $iid, \DateTime $startOfDay, \DateTime $now, \DateTimeZone $tz): ?\DateTime
    {
        $events = $this->client->resourceLabelEvents()->all($this->projectId, $iid, ['per_page' => 100]);
        $last = null;
        foreach ($events as $ev) {
            $created = new \DateTime($ev['created_at'], $tz);
            if ($created >= $startOfDay && $created <= $now && ($last === null || $created > $last)) {
                $last = $created;
            }
        }
        return $last;
    }

    /**
     * Форматирование секунд в вид "Xd Yh Zm".
     *
     * @param int $seconds
     * @return string
     */
    private function formatTime(int $seconds): string
    {
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $parts = [];
        if ($days > 0) $parts[] = "{$days}d";
        if ($hours > 0) $parts[] = "{$hours}h";
        if ($minutes > 0) $parts[] = "{$minutes}m";
        return $parts ? implode(' ', $parts) : '0m';
    }

    /**
     * Для передачи в getFilteredIssuesByTime (пример фильтра для "мало осталось")
     *
     * @return callable
     */
    private function getLowRemainingTimeFilter(): callable
    {
        return function(int $estimate, int $totalSpent): bool {
            return $estimate > 0 && $totalSpent <= $estimate && $estimate - $totalSpent <= $estimate * 0.2;
        };
    }
}
