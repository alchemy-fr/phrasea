<?php

declare(strict_types=1);

namespace App\Integration;

use Symfony\Component\Lock\LockFactory;
use Symfony\Component\RateLimiter\Exception\RateLimitExceededException;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

class ApiBudgetLimiter
{
    public const POLICIES = [
        'token_bucket',
        'fixed_window',
        'sliding_window',
        'no_limit',
    ];

    private StorageInterface $storage;
    private ?LockFactory $lockFactory;

    public function __construct(StorageInterface $storage, LockFactory $lockFactory = null)
    {
        $this->storage = $storage;
        $this->lockFactory = $lockFactory;
    }

    public function createLimiter(int $limit, string $policy, string $interval, string $key): LimiterInterface
    {
        $factory = new RateLimiterFactory([
            'id' => $key,
            'limit' => $limit,
            'policy' => $policy,
            'interval' => $interval,
        ], $this->storage, $this->lockFactory);

        return $factory->create($key);
    }

    /**
     * @throws RateLimitExceededException
     */
    public function acceptIntegrationApiCall(array $config, int $tokens = 1): void
    {
        $limiter = $this->createLimiter(
            $config['budgetLimit']['limit'],
            $config['budgetLimit']['policy'],
            $config['budgetLimit']['interval'],
            $config['workspaceIntegration']->getId()
        );

        $limiter->consume($tokens)->ensureAccepted();
    }
}
