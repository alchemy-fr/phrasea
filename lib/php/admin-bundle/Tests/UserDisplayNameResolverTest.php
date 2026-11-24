<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Tests;

use Alchemy\AdminBundle\Utils\UserDisplayNameResolver;
use Alchemy\AuthBundle\Repository\UserRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class UserDisplayNameResolverTest extends TestCase
{
    public function testNoIdDoesNotCallTheRepository(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->expects($this->never())->method('getUsersByIds');

        $this->assertSame(['names' => [], 'error' => null], (new UserDisplayNameResolver($repository, new NullLogger()))->resolve([]));
    }

    public function testResolvesNamesInASingleCall(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('getUsersByIds')
            ->with(['u1', 'u2', 'u3', 'u4', 'unknown'])
            ->willReturn([
                'u1' => ['id' => 'u1', 'username' => 'jdoe', 'firstName' => 'John', 'lastName' => 'Doe'],
                'u2' => ['id' => 'u2', 'username' => 'alice'],
                'u3' => ['id' => 'u3', 'firstName' => 'Bob'],
                'u4' => ['id' => 'u4', 'username' => ''],
            ]);

        $result = (new UserDisplayNameResolver($repository, new NullLogger()))->resolve(['u1', 'u2', 'u1', 'u3', 'u4', 'unknown']);

        $this->assertNull($result['error']);
        $this->assertSame([
            'u1' => 'jdoe (John Doe)',
            'u2' => 'alice',
            'u3' => 'Bob',
            'u4' => null,
        ], $result['names']);
        $this->assertArrayNotHasKey('unknown', $result['names']);
    }

    public function testRepositoryFailureKeepsTheIds(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->method('getUsersByIds')->willThrowException(new \RuntimeException('Keycloak is down'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $result = (new UserDisplayNameResolver($repository, $logger))->resolve(['u1']);

        $this->assertSame(['names' => [], 'error' => 'Keycloak is down'], $result);
    }
}
