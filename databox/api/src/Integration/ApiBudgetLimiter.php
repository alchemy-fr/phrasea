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
    final public const array POLICIES = [
        'token_bucket',
        'fixed_window',
        'sliding_window',
        'no_limit',
    ];

    public function __construct(private readonly StorageInterface $storage, private readonly ?LockFactory $lockFactory = null)
    {
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
    public function acceptIntegrationApiCall(IntegrationConfig $config, int $tokens = 1): void
    {
        if (!$config['budgetLimit']['enabled']) {
            return;
        }

        $limiter = $this->createLimiter(
            $config['budgetLimit']['limit'],
            $config['budgetLimit']['policy'],
            $config['budgetLimit']['interval'],
            $config->getIntegrationId()
        );

        $limiter->consume($tokens)->ensureAccepted();
    }
}
