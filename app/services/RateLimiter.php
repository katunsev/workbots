<?php
namespace App\services;

class RateLimiter extends BaseService
{
    protected string $storagePath;
    protected int $limit;
    protected int $seconds;

    public function __construct(string $storagePath, int $limit = 5, int $seconds = 60)
    {
        $this->storagePath = $storagePath;
        $this->limit = $limit;
        $this->seconds = $seconds;
        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0700, true);
        }
    }

    public function tooManyRequests(string $key): bool
    {
        $now = time();
        $file = $this->storagePath . '/rate_' . md5($key) . '.json';

        $data = [];
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true) ?: [];
            $data = array_filter($data, fn($ts) => $ts > $now - $this->seconds);
        }
        if (count($data) >= $this->limit) {
            return true;
        }
        $data[] = $now;
        file_put_contents($file, json_encode($data));
        return false;
    }
}
