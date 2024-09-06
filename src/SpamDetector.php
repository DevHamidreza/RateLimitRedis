<?php

namespace RateLimit;

use RateLimit\Predis\RedisManager;

class SpamDetector
{
    private $redisManager;
    private $spamFactorKey = 'spam_factor';
    private $lastMessageTimeKey = 'last_message_time';
    private $violationKey = 'spam_violations';
    private $blockListKey = 'blocked_ips';
    private $blockTime;
    private $violationThreshold;
    private $time;
    private $limit;

    public function __construct(int $time = 10, int $limit = 6, int $blockTime = 600, int $violationThreshold = 3)
    {
        $this->redisManager = new RedisManager();
        $this->time = $time;
        $this->limit = $limit;
        $this->blockTime = $blockTime;
        $this->violationThreshold = $violationThreshold;
    }

    public function isSpam(string $ip): bool
    {
        if ($this->isBlocked($ip)) {
            return true;
        }

        $currentTime = time();
        $spamFactor = $this->getSpamFactor($ip);
        $lastMessageTime = $this->getLastMessageTime($ip);

        $diffTime = $currentTime - $lastMessageTime;
        $spamFactor = $this->calculateSpamFactor($spamFactor, $diffTime);

        $this->updateSpamFactor($ip, $spamFactor);
        $this->updateLastMessageTime($ip, $currentTime);

        // اگر اسپم کمتر از 1 باشد، کاربر اسپمر شناخته شده و تخلف ثبت می‌شود
        if ($spamFactor < 1) {
            $this->handleViolation($ip);
            return true;
        }

        return false;
    }

    private function isBlocked(string $ip): bool
    {
        return $this->redisManager->exists("{$this->blockListKey}:{$ip}");
    }

    private function getSpamFactor(string $ip): float
    {
        return (float)$this->redisManager->get("{$this->spamFactorKey}:{$ip}") ?? 1;
    }

    private function getLastMessageTime(string $ip): int
    {
        return (int)$this->redisManager->get("{$this->lastMessageTimeKey}:{$ip}") ?? time();
    }

    private function calculateSpamFactor(float $spamFactor, int $diffTime): float
    {
        return (1 / $this->time) * $diffTime + ($this->limit - 1) / $this->limit * $spamFactor;
    }

    private function updateSpamFactor(string $ip, float $spamFactor): void
    {
        $this->redisManager->set("{$this->spamFactorKey}:{$ip}", $spamFactor);
    }

    private function updateLastMessageTime(string $ip, int $currentTime): void
    {
        $this->redisManager->set("{$this->lastMessageTimeKey}:{$ip}", $currentTime);
    }

    private function handleViolation(string $ip): void
    {
        $violations = (int)$this->redisManager->connection()->incr("{$this->violationKey}:{$ip}");
        $this->redisManager->connection()->expire("{$this->violationKey}:{$ip}", $this->blockTime);

        if ($violations >= $this->violationThreshold) {
            $this->blockIP($ip);
        }
    }

    private function blockIP(string $ip): void
    {
        $this->redisManager->set("{$this->blockListKey}:{$ip}", true, $this->blockTime);
    }
}
