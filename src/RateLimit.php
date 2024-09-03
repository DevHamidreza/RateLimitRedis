<?php

namespace Src;

use Src\Predis\RedisManager;

class RateLimit
{
    private $redisManager;
    private $limit;
    private $time;
    private $blockTime;
    private $cooldownTime;
    private $blockListKey = 'blocked_ips';
    private $violationCooldownKey = 'violation_cooldown';

    public function __construct(int $time = 10, int $limit = 6, int $blockTime = 600, int $cooldownTime = 5)
    {
        $this->redisManager = new RedisManager();
        $this->time = $time;
        $this->limit = $limit;
        $this->blockTime = $blockTime;
        $this->cooldownTime = $cooldownTime;
    }

    public function isLimited(string $page): bool
    {
        $ip = $this->getIP();

        if ($this->isBlocked($ip)) {
            return true;
        }

        if ($this->checkIsLimited($ip, $page)) {
            if (!$this->isUnderCooldown($ip)) {
                $this->handleViolation($ip);
                $this->setCooldown($ip);
            }
            return true;
        }

        return false;
    }

    private function isBlocked(string $ip): bool
    {
        return $this->redisManager->exists("{$this->blockListKey}:{$ip}");
    }

    private function isUnderCooldown(string $ip): bool
    {
        return $this->redisManager->exists("{$this->violationCooldownKey}:{$ip}");
    }

    private function setCooldown(string $ip): void
    {
        $this->redisManager->set("{$this->violationCooldownKey}:{$ip}", true, $this->cooldownTime);
    }

    private function checkIsLimited(string $ip, string $page): bool
    {
        $key = $this->getRateLimitKey($ip, $page);
        $currentTime = time();

        $timestamps = $this->redisManager->connection()->lrange($key, 0, -1);
        $timestamps = array_filter($timestamps, function ($timestamp) use ($currentTime) {
            return ($currentTime - $timestamp) < $this->time;
        });

        if (count($timestamps) >= $this->limit) {
            return true;
        } else {

            $this->redisManager->connection()->rpush($key, $currentTime);
            $this->redisManager->connection()->expire($key, $this->time);
            return false;
        }
    }

    private function handleViolation(string $ip): void
    {
        $violationsKey = "rate_limit_violations:{$ip}";
        $violations = $this->redisManager->connection()->incr($violationsKey);
        $this->redisManager->connection()->expire($violationsKey, $this->blockTime);

        if ($violations > 3) {
            $this->redisManager->set("{$this->blockListKey}:{$ip}", true, $this->blockTime);
        }
    }

    private function getRateLimitKey(string $ip, string $page): string
    {
        return "rate_limit_{$page}:{$ip}";
    }

    private function getIP(): string
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        }

        return $_SERVER['REMOTE_ADDR'];
    }
}
