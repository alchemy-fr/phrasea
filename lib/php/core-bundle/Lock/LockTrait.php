<?php

namespace Alchemy\CoreBundle\Lock;

use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Contracts\Service\Attribute\Required;

trait LockTrait
{
    protected LockFactory $lockFactory;

    #[Required]
    public function setLockFactory(LockFactory $lockFactory): void
    {
        $this->lockFactory = $lockFactory;
    }

    /**
     * @param int                            $retryAfter seconds after which a caller refused by the lock may retry
     * @param callable(LockInterface): mixed $callback   receives the lock, to refresh it during a long task
     * @param int|null                       $ttl        lifetime of the lock (defaults to $retryAfter)
     */
    public function executeWithLock(string $resource, int $retryAfter, string $message, callable $callback, ?int $ttl = null): mixed
    {
        $lock = $this->lockFactory->createLock($resource, ttl: $ttl ?? $retryAfter);

        if (!$lock->acquire()) {
            throw new TooManyRequestsHttpException($retryAfter, $message);
        }

        try {
            return $callback($lock);
        } finally {
            $lock->release();
        }
    }
}
