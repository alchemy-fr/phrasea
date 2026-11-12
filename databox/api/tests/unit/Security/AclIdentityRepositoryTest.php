<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use Alchemy\AuthBundle\Repository\AclGroupRepository;
use Alchemy\AuthBundle\Repository\AclUserRepository;
use Alchemy\AuthBundle\Repository\GroupRepositoryInterface;
use Alchemy\AuthBundle\Repository\IdentityProjection;
use Alchemy\AuthBundle\Repository\UserRepositoryInterface;
use Alchemy\AuthBundle\Security\JwtUser;
use PHPUnit\Framework\TestCase;

/**
 * The users and groups given to acl-bundle (serialized in the ACEs) are
 * reduced to their display fields.
 */
final class AclIdentityRepositoryTest extends TestCase
{
    private const array FULL_USER = [
        'id' => 'user-id',
        'username' => 'alice',
        'firstName' => 'Alice',
        'lastName' => 'Liddell',
        'email' => 'alice@phrasea.test',
        'emailVerified' => true,
        'enabled' => true,
        'attributes' => ['locale' => ['fr']],
        'createdTimestamp' => 1700000000000,
    ];

    private const array FULL_GROUP = [
        'id' => 'group-id',
        'name' => 'editors',
        'path' => '/editors',
        'attributes' => ['budget' => ['42']],
        'subGroups' => [],
        'realmRoles' => ['databox'],
    ];

    public function testGetUserKeepsTheDisplayFieldsOnly(): void
    {
        $users = $this->createMock(UserRepositoryInterface::class);
        $users->expects($this->once())
            ->method('getUser')
            ->with('user-id', ['x' => 1])
            ->willReturn(self::FULL_USER);

        $this->assertSame([
            'id' => 'user-id',
            'username' => 'alice',
            'firstName' => 'Alice',
            'lastName' => 'Liddell',
        ], new AclUserRepository($users)->getUser('user-id', ['x' => 1]));
    }

    public function testUnknownUserStaysNull(): void
    {
        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('getUser')->willReturn(null);

        $this->assertNull(new AclUserRepository($users)->getUser('unknown'));
    }

    public function testGetUsersAndAclGroupsAreDelegated(): void
    {
        $user = new JwtUser('jwt', 'user-id', 'alice', groups: ['g1', 'g2']);
        $users = $this->createMock(UserRepositoryInterface::class);
        $users->expects($this->once())
            ->method('getUsers')
            ->with(['query' => ['max' => 10]])
            ->willReturn([self::FULL_USER]);
        $users->expects($this->once())
            ->method('getAclGroupsId')
            ->with($user)
            ->willReturn(['g1', 'g2']);

        $repository = new AclUserRepository($users);
        $this->assertSame([self::FULL_USER], $repository->getUsers(['query' => ['max' => 10]]));
        $this->assertSame(['g1', 'g2'], $repository->getAclGroupsId($user));
    }

    public function testGetGroupKeepsTheDisplayFieldsOnly(): void
    {
        $groups = $this->createMock(GroupRepositoryInterface::class);
        $groups->expects($this->once())
            ->method('getGroup')
            ->with('group-id', [])
            ->willReturn(self::FULL_GROUP);

        $this->assertSame([
            'id' => 'group-id',
            'name' => 'editors',
            'path' => '/editors',
        ], new AclGroupRepository($groups)->getGroup('group-id'));
    }

    public function testUnknownGroupStaysNull(): void
    {
        $groups = $this->createStub(GroupRepositoryInterface::class);
        $groups->method('getGroup')->willReturn(null);

        $this->assertNull(new AclGroupRepository($groups)->getGroup('unknown'));
    }

    public function testGetGroupsIsDelegated(): void
    {
        $groups = $this->createMock(GroupRepositoryInterface::class);
        $groups->expects($this->once())
            ->method('getGroups')
            ->with(['access_token' => 'token'])
            ->willReturn([self::FULL_GROUP]);

        $this->assertSame([self::FULL_GROUP], new AclGroupRepository($groups)->getGroups(['access_token' => 'token']));
    }

    public function testUsersKeepTheirKeys(): void
    {
        $this->assertSame([
            'user-id' => ['id' => 'user-id', 'username' => 'alice', 'firstName' => 'Alice', 'lastName' => 'Liddell'],
            'missing' => null,
        ], IdentityProjection::users([
            'user-id' => self::FULL_USER,
            'missing' => null,
        ]));
    }
}
