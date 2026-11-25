<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Tests;

use Alchemy\AdminBundle\Utils\UserChoiceLoader;
use Alchemy\AuthBundle\Repository\UserRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;

class UserChoiceLoaderTest extends TestCase
{
    /**
     * @return list<array<string, string>>
     */
    public static function createUsers(int $count): array
    {
        $users = [];
        for ($i = 0; $i < $count; ++$i) {
            $users[] = ['id' => 'u'.$i, 'username' => 'user'.$i];
        }

        return $users;
    }

    public function testListsTheUsersPageByPage(): void
    {
        $users = self::createUsers(2 * UserChoiceLoader::PAGE_SIZE + 150);
        $users[0] = ['id' => 'u0', 'username' => 'jdoe', 'firstName' => 'John', 'lastName' => 'Doe'];

        $repository = $this->createMock(UserRepositoryInterface::class);
        $requestedPages = [];
        $repository->expects($this->exactly(3))
            ->method('getUsers')
            ->willReturnCallback(function (array $options) use ($users, &$requestedPages): array {
                $requestedPages[] = [$options['query']['first'], $options['query']['max']];

                return array_slice($users, $options['query']['first'], $options['query']['max']);
            });

        $result = (new UserChoiceLoader($repository, new ArrayAdapter(), new NullLogger()))->getUsers();

        $this->assertSame([
            [0, UserChoiceLoader::PAGE_SIZE],
            [UserChoiceLoader::PAGE_SIZE, UserChoiceLoader::PAGE_SIZE],
            [2 * UserChoiceLoader::PAGE_SIZE, UserChoiceLoader::PAGE_SIZE],
        ], $requestedPages);
        $this->assertCount(2 * UserChoiceLoader::PAGE_SIZE + 150, $result);
        $this->assertSame('jdoe (John Doe)', $result['u0']);
        $lastId = 'u'.(2 * UserChoiceLoader::PAGE_SIZE + 149);
        $this->assertSame('user'.(2 * UserChoiceLoader::PAGE_SIZE + 149), $result[$lastId]);
    }

    public function testStopsWhenTheOffsetIsIgnored(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->expects($this->exactly(2))
            ->method('getUsers')
            ->willReturn(self::createUsers(UserChoiceLoader::PAGE_SIZE));

        $result = (new UserChoiceLoader($repository, new ArrayAdapter(), new NullLogger()))->getUsers();

        $this->assertCount(UserChoiceLoader::PAGE_SIZE, $result);
    }

    public function testCachesTheUsersForFiveMinutes(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->expects($this->exactly(2))
            ->method('getUsers')
            ->willReturn([['id' => 'u1', 'username' => 'alice']]);

        $clock = new MockClock();
        $loader = new UserChoiceLoader($repository, new ArrayAdapter(clock: $clock), new NullLogger());

        $this->assertSame(['u1' => 'alice'], $loader->getUsers());
        $clock->sleep(UserChoiceLoader::CACHE_TTL - 1);
        $this->assertSame(['u1' => 'alice'], $loader->getUsers());
        $clock->sleep(2);
        $this->assertSame(['u1' => 'alice'], $loader->getUsers());
    }

    public function testFailureReturnsNullAndIsNotCached(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $calls = 0;
        $repository->expects($this->exactly(2))
            ->method('getUsers')
            ->willReturnCallback(function () use (&$calls): array {
                if (1 === ++$calls) {
                    throw new \RuntimeException('Keycloak is down');
                }

                return [['id' => 'u1', 'username' => 'alice']];
            });
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $loader = new UserChoiceLoader($repository, new ArrayAdapter(), $logger);

        $this->assertNull($loader->getUsers());
        $this->assertSame(['u1' => 'alice'], $loader->getUsers());
    }
}
