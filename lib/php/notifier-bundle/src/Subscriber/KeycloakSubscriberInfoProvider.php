<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Subscriber;

use Alchemy\AuthBundle\Repository\UserRepositoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class KeycloakSubscriberInfoProvider implements SubscriberInfoProviderInterface
{
    private LoggerInterface $logger;

    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
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

        return new SubscriberInfo(
            email: $user['email'] ?? null,
            phoneNumber: $this->firstAttribute($user, 'phoneNumber'),
            locale: $this->firstAttribute($user, 'locale'),
            displayName: $this->resolveDisplayName($user),
        );
    }

    private function resolveDisplayName(array $user): ?string
    {
        $parts = array_filter([$user['firstName'] ?? null, $user['lastName'] ?? null]);
        if ([] !== $parts) {
            return implode(' ', $parts);
        }

        return $user['username'] ?? $user['email'] ?? null;
    }

    private function firstAttribute(array $user, string $key): ?string
    {
        $value = $user['attributes'][$key] ?? null;
        if (is_array($value)) {
            $value = $value[0] ?? null;
        }

        return null !== $value ? (string) $value : null;
    }
}
