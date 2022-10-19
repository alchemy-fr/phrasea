<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Tests;

use Alchemy\AclBundle\Entity\AccessControlEntry;
use Alchemy\AclBundle\Mapping\ObjectMapping;
use Alchemy\AclBundle\Repository\PermissionRepositoryInterface;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AclBundle\Security\PermissionManager;
use Alchemy\AclBundle\Tests\Mock\ObjectMock;
use Alchemy\AclBundle\Tests\Mock\UserMock;
use PHPUnit\Framework\TestCase;

class PermissionTest extends TestCase
{
    /**
     * @dataProvider permissionProvider
     */
    public function testPermissionsWithUser(array $acePermissions, int $permissionToTest, bool $expectedResult)
    {
        $ace = $this->createAce(AccessControlEntry::TYPE_USER_VALUE, '123', 'pub', '42', $acePermissions);

        $objectMapper = $this->createMock(ObjectMapping::class);
        $objectMapper
            ->expects($this->once())
            ->method('getObjectKey')
            ->willReturn('pub');

        $permissionRepo = $this->createMock(PermissionRepositoryInterface::class);
        $permissionRepo
            ->expects($this->once())
            ->method('getAces')
            ->willReturn([$ace]);

        $permissionManager = new PermissionManager($objectMapper, $permissionRepo);

        $user = new UserMock('123', []);
        $object = new ObjectMock('42');

        $this->assertEquals($expectedResult, $permissionManager->isGranted($user, $object, $permissionToTest));
    }

    /**
     * @dataProvider permissionProvider
     */
    public function testPermissionsWithGroup(array $acePermissions, int $permissionToTest, bool $expectedResult)
    {
        $userAce = $this->createAce(AccessControlEntry::TYPE_USER_VALUE, '123', 'pub', '42', []);
        $ace = $this->createAce(AccessControlEntry::TYPE_GROUP_VALUE, 'group1', 'pub', '42', $acePermissions);

        $objectMapper = $this->createMock(ObjectMapping::class);
        $objectMapper
            ->expects($this->once())
            ->method('getObjectKey')
            ->willReturn('pub');

        $permissionRepo = $this->createMock(PermissionRepositoryInterface::class);
        $permissionRepo
            ->expects($this->once())
            ->method('getAces')
            ->with($this->equalTo('123'), $this->equalTo(['group1']), $this->equalTo('pub'), $this->equalTo('42'))
            ->willReturn([
                $userAce,
                $ace,
            ]);

        $permissionManager = new PermissionManager($objectMapper, $permissionRepo);

        $user = new UserMock('123', [
            'group1',
        ]);
        $object = new ObjectMock('42');

        $this->assertEquals($expectedResult, $permissionManager->isGranted($user, $object, $permissionToTest));
    }

    public function permissionProvider(): array
    {
        return [
            [[], PermissionInterface::VIEW, false],
            [[
                PermissionInterface::DELETE,
                PermissionInterface::EDIT,
            ], PermissionInterface::EDIT, true],
            [[
                PermissionInterface::DELETE,
                PermissionInterface::EDIT,
            ], PermissionInterface::VIEW, false],
        ];
    }

    private function createAce(int $userType, string $userId, string $objectType, string $objectId, array $permissions): AccessControlEntry
    {
        $ace = new AccessControlEntry();
        $ace->setObjectType($objectType);
        $ace->setObjectId($objectId);
        $ace->setUserType($userType);
        $ace->setUserId($userId);

        foreach ($permissions as $permission) {
            $ace->addPermission($permission);
        }

        return $ace;
    }
}
