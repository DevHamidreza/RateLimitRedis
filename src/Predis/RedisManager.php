<?php

namespace RateLimit\Predis;

use Predis\Client as PredisClient;
use Predis\Connection\ConnectionException;


class RedisManager
{

    private $connection;

    public function __construct(array $parameters = [])
    {
        if(empty($parameters)){
            $env = parse_ini_file('.env');
            $parameters = $this->defaultConfig($env);
        }
        $this->connection = new PredisClient($parameters);
    }

    private function defaultConfig($env): array
    {
        return [
            'scheme' => 'tcp',
            'host' => $env['REDIS_HOST'] ?? '127.0.0.1',
            'port' => $env['REDIS_PORT'] ?? 6379,
            'password' =>  $env['REDIS_PASSWORD'] ?? null
        ];
    }

    public function connection(): PredisClient
    {
        return $this->connection;
    }

    public function set(string $key, mixed $value,int $expire = 0): mixed
    {
        try {
            $result = $this->connection->set($key, $value);
            if ($expire > 0) {
                $this->connection->expire($key, $expire);
            }
            return $result;
        } catch (ConnectionException $e) {
            return false;
        }
    }

    public function get(string $key): mixed
    {
        try {
            return $this->connection->get($key);
        } catch (ConnectionException $e) {
            return false;
        }
    }

    public function delete(string $key): false|int
    {
        try {
            return $this->connection->del([$key]);
        } catch (ConnectionException $e) {
            return false;
        }
    }

    public function exists(string $key): bool
    {
        try {
            return $this->connection->exists($key);
        } catch (ConnectionException $e) {
            return false;
        }
    }
}


