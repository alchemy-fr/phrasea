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
    public function testPermissions(array $acePermissions, int $permissionToTest, bool $expectedResult)
    {
        $ace = $this->createAce('pub:42', '123', $acePermissions);

        $objectMapper = $this->createMock(ObjectMapping::class);
        $objectMapper
            ->expects($this->once())
            ->method('getObjectKey')
            ->willReturn('pub');

        $permissionRepo = $this->createMock(PermissionRepositoryInterface::class);
        $permissionRepo
            ->expects($this->once())
            ->method('getAce')
            ->willReturn($ace);

        $permissionManager = new PermissionManager($objectMapper, $permissionRepo);

        $user = new UserMock('123');
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

    private function createAce(string $objectId, string $userId, array $permissions): AccessControlEntry
    {
        $ace = new AccessControlEntry();
        $ace->setObject($objectId);
        $ace->setUserId($userId);

        foreach ($permissions as $permission) {
            $ace->addPermission($permission);
        }

        return $ace;
    }
}
