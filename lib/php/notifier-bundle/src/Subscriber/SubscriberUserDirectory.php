<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Subscriber;

use Alchemy\NotifierBundle\Repository\SubscriberRepository;

/**
 * Restricts a broadcast to the users already known locally, i.e. those who
 * received at least one notification before. Useful when the realm is shared
 * with other applications, or when Keycloak is unreachable (dev/test).
 */
final readonly class SubscriberUserDirectory implements UserDirectoryInterface
{
    public const string NAME = 'subscribers';

    public function __construct(
        private SubscriberRepository $repository,
    ) {
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getLabel(): string
    {
        return 'Known subscribers only';
    }

    public function iterate(): iterable
    {
        foreach ($this->repository->iterateUserIds() as $userId) {
            yield new DirectoryUser($userId);
        }
    }
}
