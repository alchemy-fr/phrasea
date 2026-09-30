<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use Alchemy\AuthBundle\Repository\UserRepositoryInterface;
use Alchemy\AuthBundle\Security\Impersonator;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\RoleMapper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ImpersonatorTest extends TestCase
{
    private const string ADMIN_ID = 'admin-id';
    private const string TARGET_ID = 'target-id';

    public function testAdminGetsOnlyTheTargetPermissions(): void
    {
        $user = $this->createImpersonator()->impersonate($this->createAdmin(), self::TARGET_ID);

        $this->assertSame(self::TARGET_ID, $user->getId());
        $this->assertSame('alice', $user->getUsername());
        $this->assertSame([JwtUser::ROLE_TECH], $user->getRoles());
        $this->assertSame(['group-1'], $user->getGroups());
        $this->assertSame(self::ADMIN_ID, $user->getImpersonatorId());
        $this->assertTrue($user->isImpersonated());
        $this->assertSame('admin-jwt', $user->getJwt());
    }

    public function testAdminTargetKeepsAdminRole(): void
    {
        $user = $this->createImpersonator(targetRoles: ['databox', 'databox-admin'])
            ->impersonate($this->createAdmin(), self::TARGET_ID);

        $this->assertSame([JwtUser::ROLE_ADMIN], $user->getRoles());
    }

    public function testNonAdminIsDenied(): void
    {
        $this->expectException(AccessDeniedHttpException::class);

        $this->createImpersonator()->impersonate(
            new JwtUser('jwt', 'user-id', 'user', [], [], []),
            self::TARGET_ID,
        );
    }

    public function testImpersonatedUserCannotImpersonateAgain(): void
    {
        $impersonator = $this->createImpersonator(targetRoles: ['databox', 'admin']);
        $impersonated = $impersonator->impersonate($this->createAdmin(), self::TARGET_ID);

        $this->assertFalse($impersonator->canImpersonate($impersonated));
    }

    public function testDisabledFeatureIsDenied(): void
    {
        $this->expectException(AccessDeniedHttpException::class);

        $this->createImpersonator(enabled: false)->impersonate($this->createAdmin(), self::TARGET_ID);
    }

    public function testTargetWithoutRequiredRoleIsDenied(): void
    {
        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('databox');

        $this->createImpersonator(targetRoles: ['expose'])->impersonate($this->createAdmin(), self::TARGET_ID);
    }

    public function testUnknownTargetIsDenied(): void
    {
        $this->expectException(AccessDeniedHttpException::class);

        $this->createImpersonator(target: null)->impersonate($this->createAdmin(), self::TARGET_ID);
    }

    public function testDisabledTargetIsDenied(): void
    {
        $this->expectException(AccessDeniedHttpException::class);

        $this->createImpersonator(target: ['username' => 'alice', 'enabled' => false])
            ->impersonate($this->createAdmin(), self::TARGET_ID);
    }

    public function testInvalidTargetIdIsDenied(): void
    {
        $this->expectException(AccessDeniedHttpException::class);

        $this->createImpersonator()->impersonate($this->createAdmin(), '../users');
    }

    private function createAdmin(): JwtUser
    {
        return new JwtUser('admin-jwt', self::ADMIN_ID, 'admin', [JwtUser::ROLE_ADMIN], ['admin-group'], ['openid']);
    }

    private function createImpersonator(
        bool $enabled = true,
        ?array $target = ['username' => 'alice', 'enabled' => true],
        array $targetRoles = ['databox', 'tech'],
    ): Impersonator {
        $repository = $this->createStub(UserRepositoryInterface::class);
        $repository->method('getUser')->willReturn($target);
        $repository->method('getUserRoles')->willReturn($targetRoles);
        $repository->method('getUserGroupIds')->willReturn(['group-1']);

        return new Impersonator(
            $repository,
            new RoleMapper('databox'),
            $enabled,
            ['databox'],
        );
    }
}
