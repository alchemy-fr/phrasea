<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use Symfony\Component\Cache\Adapter\RedisAdapter;

/**
 * The few Redis commands the populate bookkeeping needs (locks, owners, write journal),
 * whatever the client behind the DSN (phpredis or Predis, whose signatures differ).
 */
final class PopulateRedis
{
    private \Redis|\Predis\ClientInterface|null $connection = null;

    public function __construct(
        private readonly string $redisDsn,
    ) {
    }

    public function exists(string $key): bool
    {
        return (int) $this->connection()->exists($key) > 0;
    }

    public function get(string $key): ?string
    {
        $value = $this->connection()->get($key);

        return \is_string($value) ? $value : null;
    }

    public function setEx(string $key, int $ttl, string $value): void
    {
        $this->connection()->setex($key, $ttl, $value);
    }

    /**
     * @param list<string> $keys
     *
     * @return int the number of deleted keys
     */
    public function del(array $keys): int
    {
        if ([] === $keys) {
            return 0;
        }

        return (int) $this->connection()->del($keys);
    }

    public function rename(string $from, string $to): void
    {
        $this->connection()->rename($from, $to);
    }

    /**
     * @param list<string> $members
     */
    public function sAdd(string $key, array $members): void
    {
        if ([] === $members) {
            return;
        }

        $connection = $this->connection();
        if ($connection instanceof \Redis) {
            $connection->sAdd($key, ...$members);
        } else {
            $connection->sadd($key, $members);
        }
    }

    /**
     * Removes and returns up to $count members of the set.
     *
     * @return list<string>
     */
    public function sPop(string $key, int $count): array
    {
        $connection = $this->connection();
        $members = $connection instanceof \Redis ? $connection->sPop($key, $count) : $connection->spop($key, $count);

        return \is_array($members) ? array_values(array_map(strval(...), $members)) : [];
    }

    public function rPush(string $key, string $value): void
    {
        $connection = $this->connection();
        if ($connection instanceof \Redis) {
            $connection->rPush($key, $value);
        } else {
            $connection->rpush($key, [$value]);
        }
    }

    public function lPop(string $key): ?string
    {
        $value = $this->connection()->lpop($key);

        return \is_string($value) ? $value : null;
    }

    private function connection(): \Redis|\Predis\ClientInterface
    {
        if (null === $this->connection) {
            $connection = RedisAdapter::createConnection($this->redisDsn);
            if (!$connection instanceof \Redis && !$connection instanceof \Predis\ClientInterface) {
                throw new \LogicException(\sprintf('Unsupported Redis client "%s" for the populate lock store.', get_debug_type($connection)));
            }
            $this->connection = $connection;
        }

        return $this->connection;
    }
}
