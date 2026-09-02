<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Subscriber;

use Alchemy\AuthBundle\Repository\UserRepositoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(SubscriberInfoProviderInterface::class)]
final class KeycloakSubscriberInfoProvider implements SubscriberInfoProviderInterface
{
    private LoggerInterface $logger;

    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly KeycloakUserMapper $mapper,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function getInfo(string $userId): ?SubscriberInfo
    {
        try {
            $user = $this->userRepository->getUser($userId);
        } catch (\Throwable $e) {
            $this->logger->error(sprintf('Failed to resolve user "%s": %s', $userId, $e->getMessage()));

            return null;
        }

        if (null === $user) {
            return null;
        }

        return $this->mapper->map($user);
    }
}
