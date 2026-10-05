<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Subscriber;

use Alchemy\AuthBundle\Repository\UserRepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Streams every enabled user of the Keycloak realm, page by page.
 */
final readonly class KeycloakUserDirectory implements UserDirectoryInterface
{
    public const string NAME = 'keycloak';

    public function __construct(
        private UserRepositoryInterface $userRepository,
        private KeycloakUserMapper $mapper,
        #[Autowire(param: 'alchemy_notifier.directory_batch_size')]
        private int $batchSize = 100,
    ) {
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getLabel(): string
    {
        return 'All users (Keycloak)';
    }

    public function iterate(): iterable
    {
        $first = 0;

        while (true) {
            $users = $this->fetchPage($first);
            if ([] === $users) {
                return;
            }

            foreach ($users as $user) {
                $userId = $user['id'] ?? null;
                if (null === $userId || '' === $userId) {
                    continue;
                }

                if (false === ($user['enabled'] ?? true)) {
                    continue;
                }

                yield new DirectoryUser((string) $userId, $this->mapper->map($user));
            }

            // A short page means we reached the end of the realm
            if (count($users) < $this->batchSize) {
                return;
            }

            $first += $this->batchSize;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchPage(int $first): array
    {
        return $this->userRepository->getUsers([
            'query' => [
                'first' => $first,
                'max' => $this->batchSize,
                'enabled' => 'true',
                // Without it Keycloak omits the custom attributes (locale, phoneNumber)
                'briefRepresentation' => 'false',
            ],
        ]);
    }
}
