<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Tests\Subscriber;

use Alchemy\AuthBundle\Repository\UserRepositoryInterface;
use Alchemy\NotifierBundle\Subscriber\DirectoryUser;
use Alchemy\NotifierBundle\Subscriber\KeycloakUserDirectory;
use Alchemy\NotifierBundle\Subscriber\KeycloakUserMapper;
use PHPUnit\Framework\TestCase;

final class KeycloakUserDirectoryTest extends TestCase
{
    public function testItPagesThroughTheWholeRealm(): void
    {
        $queries = [];
        $pages = [
            [$this->user('u1'), $this->user('u2')],
            [$this->user('u3')],
        ];

        $directory = $this->directory($pages, $queries, batchSize: 2);

        self::assertSame(['u1', 'u2', 'u3'], $this->userIds($directory));
        self::assertSame([0, 2], array_column($queries, 'first'));
        self::assertSame([2, 2], array_column($queries, 'max'));
    }

    public function testItStopsOnAnEmptyPage(): void
    {
        $queries = [];
        $directory = $this->directory([
            [$this->user('u1'), $this->user('u2')],
            [],
        ], $queries, batchSize: 2);

        self::assertSame(['u1', 'u2'], $this->userIds($directory));
        self::assertCount(2, $queries);
    }

    public function testDisabledAndIdLessUsersAreSkipped(): void
    {
        $queries = [];
        $directory = $this->directory([[
            $this->user('u1'),
            ['id' => 'u2', 'enabled' => false],
            ['username' => 'no-id'],
        ]], $queries, batchSize: 10);

        self::assertSame(['u1'], $this->userIds($directory));
    }

    public function testContactInfoComesFromTheListing(): void
    {
        $queries = [];
        $directory = $this->directory([[[
            'id' => 'u1',
            'email' => 'jane@example.com',
            'firstName' => 'Jane',
            'lastName' => 'Doe',
            'attributes' => ['locale' => ['fr'], 'phoneNumber' => ['+33600000000']],
        ]]], $queries, batchSize: 10);

        /** @var DirectoryUser $user */
        $user = iterator_to_array($directory->iterate())[0];

        self::assertSame('u1', $user->userId);
        self::assertSame('jane@example.com', $user->info?->email);
        self::assertSame('Jane Doe', $user->info?->displayName);
        self::assertSame('fr', $user->info?->locale);
        self::assertSame('+33600000000', $user->info?->phoneNumber);
    }

    public function testItAsksForTheFullRepresentationOfEnabledUsersOnly(): void
    {
        $queries = [];
        $directory = $this->directory([[]], $queries, batchSize: 10);
        $this->userIds($directory);

        self::assertSame('true', $queries[0]['enabled']);
        self::assertSame('false', $queries[0]['briefRepresentation']);
    }

    /**
     * @return array<int, string>
     */
    private function userIds(KeycloakUserDirectory $directory): array
    {
        return array_map(static fn (DirectoryUser $u): string => $u->userId, iterator_to_array($directory->iterate(), false));
    }

    /**
     * @return array<string, mixed>
     */
    private function user(string $id): array
    {
        return ['id' => $id, 'email' => $id.'@example.com'];
    }

    /**
     * @param array<int, array<int, array<string, mixed>>> $pages
     * @param array<int, array<string, mixed>>             $queries
     */
    private function directory(array $pages, array &$queries, int $batchSize): KeycloakUserDirectory
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->method('getUsers')->willReturnCallback(
            function (array $options) use (&$pages, &$queries): array {
                $queries[] = $options['query'];

                return array_shift($pages) ?? [];
            }
        );

        return new KeycloakUserDirectory($repository, new KeycloakUserMapper(), $batchSize);
    }
}
