<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Utils;

use Alchemy\AuthBundle\Repository\UserRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Lists every user of the realm for the admin user choices, page by page since the identity
 * provider returns at most 100 users per call by default.
 */
final readonly class UserChoiceLoader
{
    public const int PAGE_SIZE = 500;
    public const int CACHE_TTL = 300;
    private const string CACHE_KEY = 'admin_user_choices';

    public function __construct(
        private UserRepositoryInterface $userRepository,
        #[Target]
        private CacheInterface $keycloakRealmCache,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return array<string, string>|null display names indexed by user ID, null when the users
     *                                    could not be fetched
     */
    public function getUsers(): ?array
    {
        try {
            return $this->keycloakRealmCache->get(self::CACHE_KEY, function (ItemInterface $item): array {
                $item->expiresAfter(self::CACHE_TTL);

                return $this->fetchUsers();
            });
        } catch (\Throwable $e) {
            $this->logger->warning('Cannot list the users: {message}', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            return null;
        }
    }

    /**
     * @return array<string, string>
     */
    private function fetchUsers(): array
    {
        $users = [];
        for ($first = 0;; $first += self::PAGE_SIZE) {
            $page = $this->userRepository->getUsers([
                'query' => [
                    'first' => $first,
                    'max' => self::PAGE_SIZE,
                    'briefRepresentation' => 'true',
                ],
            ]);

            $count = count($users);
            foreach ($page as $user) {
                $id = (string) $user['id'];
                $users[$id] = UserDisplayNameResolver::getDisplayName($user) ?? $id;
            }

            // A short page is the last one; a page that brings no new user means that the
            // offset is ignored, and fetching further would loop forever.
            if (count($page) < self::PAGE_SIZE || count($users) === $count) {
                return $users;
            }
        }
    }
}
