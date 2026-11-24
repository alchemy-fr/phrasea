<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Utils;

use Alchemy\AuthBundle\Repository\UserRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Resolves the display names of users from their IDs, in a single call to the identity provider.
 */
final readonly class UserDisplayNameResolver
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * "names" holds an entry (null when the user has no name) for each user found: a missing ID
     * is an unknown user, unless "error" is set because the users could not be fetched.
     *
     * @param list<string> $ids
     *
     * @return array{names: array<string, ?string>, error: ?string}
     */
    public function resolve(array $ids): array
    {
        $ids = array_values(array_unique(array_map(strval(...), $ids)));
        if ([] === $ids) {
            return ['names' => [], 'error' => null];
        }

        try {
            $users = $this->userRepository->getUsersByIds($ids);
        } catch (\Throwable $e) {
            $this->logger->warning('Cannot resolve the user names: {message}', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            return ['names' => [], 'error' => $e->getMessage()];
        }

        $names = [];
        foreach ($ids as $id) {
            if (null !== $user = $users[$id] ?? null) {
                $names[$id] = self::getDisplayName($user);
            }
        }

        return ['names' => $names, 'error' => null];
    }

    public static function getDisplayName(array $user): ?string
    {
        $fullName = trim(($user['firstName'] ?? '').' '.($user['lastName'] ?? ''));
        $username = $user['username'] ?? null;

        if (null === $username || '' === $username) {
            return '' !== $fullName ? $fullName : null;
        }

        return '' !== $fullName ? sprintf('%s (%s)', $username, $fullName) : $username;
    }
}
