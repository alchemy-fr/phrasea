<?php

namespace Alchemy\CoreBundle\Lock;

use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Contracts\Service\Attribute\Required;

trait LockTrait
{
    protected LockFactory $lockFactory;

    #[Required]
    public function setLockFactory(LockFactory $lockFactory): void
    {
        $this->lockFactory = $lockFactory;
    }

    public function executeWithLock(string $resource, int $retryAfter, string $message, callable $callback): mixed
    {
        $lock = $this->lockFactory->createLock($resource, ttl: $retryAfter);

        if (!$lock->acquire()) {
            throw new TooManyRequestsHttpException($retryAfter, $message);
        }

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }
}
