<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use Alchemy\AuthBundle\Repository\UserRepositoryInterface;
use Alchemy\AuthBundle\Security\Impersonator;
use Alchemy\AuthBundle\Security\RoleMapper;
use App\Security\IntegrationOwnerProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class IntegrationOwnerProviderTest extends TestCase
{
    private const string OWNER_ID = 'owner-id';

    private int $userCalls = 0;

    public function testTheOwnerIsReadFromKeycloakOnceWithinTheTtl(): void
    {
        $provider = $this->createProvider(['username' => 'alice', 'enabled' => true]);

        $expected = ['username' => 'alice', 'roles' => ['databox'], 'groups' => ['group-1']];
        $this->assertSame($expected, $provider->getOwner(self::OWNER_ID));
        $this->assertSame($expected, $provider->getOwner(self::OWNER_ID));
        $this->assertSame(1, $this->userCalls);

        $provider->getOwner('other-owner');
        $this->assertSame(2, $this->userCalls);
    }

    public function testARefusedOwnerIsNotKept(): void
    {
        $provider = $this->createProvider(['username' => 'alice', 'enabled' => false]);

        for ($i = 1; $i <= 2; ++$i) {
            try {
                $provider->getOwner(self::OWNER_ID);
                $this->fail('A disabled owner must be refused');
            } catch (AccessDeniedHttpException) {
            }
            $this->assertSame($i, $this->userCalls);
        }
    }

    private function createProvider(array $owner): IntegrationOwnerProvider
    {
        $repository = $this->createStub(UserRepositoryInterface::class);
        $repository->method('getUser')->willReturnCallback(function () use ($owner): array {
            ++$this->userCalls;

            return $owner;
        });
        $repository->method('getUserRoles')->willReturn(['databox']);
        $repository->method('getUserGroupIds')->willReturn(['group-1']);

        return new IntegrationOwnerProvider(
            new Impersonator($repository, new RoleMapper('databox'), ['databox']),
            new ArrayAdapter(),
        );
    }
}
