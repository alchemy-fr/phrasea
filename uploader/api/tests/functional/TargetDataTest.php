<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AclBundle\Security\PermissionManager;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Target;
use App\Security\Acl\TargetDataAclObject;
use Symfony\Contracts\HttpClient\ResponseInterface;

class TargetDataTest extends AbstractUploaderTestCase
{
    public function testEditDataAsAdmin(): void
    {
        $targetId = $this->getOrCreateDefaultTarget()->getId();

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'GET', '/targets/'.$targetId.'/data');
        $this->assertEquals(200, $response->getStatusCode(), $response->getContent(false));
        $this->assertSame('null', $response->getContent());

        $response = $this->putData(KeycloakClientTestMock::ADMIN_UID, $targetId, ['foo' => 'bar', 'n' => 42]);
        $this->assertEquals(200, $response->getStatusCode(), $response->getContent(false));
        $this->assertSame(['foo' => 'bar', 'n' => 42], $response->toArray());

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'GET', '/targets/'.$targetId.'/data');
        $this->assertEquals(200, $response->getStatusCode(), $response->getContent(false));
        $this->assertSame(['foo' => 'bar', 'n' => 42], $response->toArray());

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'GET', '/targets/'.$targetId);
        $this->assertTrue($response->toArray()['hasData']);
        $this->assertArrayNotHasKey('data', $response->toArray());

        $response = $this->putData(KeycloakClientTestMock::ADMIN_UID, $targetId, null);
        $this->assertEquals(200, $response->getStatusCode(), $response->getContent(false));
        $this->assertSame('null', $response->getContent());

        $em = self::getEntityManager();
        $em->clear();
        $this->assertNull($em->find(Target::class, $targetId)->getData());
    }

    public function testEmptyDataIsNoData(): void
    {
        $targetId = $this->getOrCreateDefaultTarget()->getId();

        $response = $this->putData(KeycloakClientTestMock::ADMIN_UID, $targetId, []);
        $this->assertEquals(200, $response->getStatusCode(), $response->getContent(false));
        $this->assertSame('null', $response->getContent());
    }

    public function testDataMustBeAnObject(): void
    {
        $response = $this->putData(KeycloakClientTestMock::ADMIN_UID, $this->getOrCreateDefaultTarget()->getId(), ['a', 'b']);
        $this->assertEquals(422, $response->getStatusCode(), $response->getContent(false));
        $this->assertSame('data', $response->toArray(false)['violations'][0]['propertyPath']);
    }

    public function testEditDataWithTheGlobalPermission(): void
    {
        $targetId = $this->getOrCreateDefaultTarget()->getId();

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'GET', '/targets/'.$targetId.'/data');
        $this->assertEquals(403, $response->getStatusCode());
        $response = $this->putData(KeycloakClientTestMock::USER_UID, $targetId, ['foo' => 'bar']);
        $this->assertEquals(403, $response->getStatusCode());

        self::getContainer()->get(PermissionManager::class)->updateOrCreateAce(
            AccessControlEntryInterface::TYPE_USER_VALUE,
            KeycloakClientTestMock::USER_UID,
            TargetDataAclObject::OBJECT_KEY,
            null,
            PermissionInterface::EDIT,
        );

        $response = $this->putData(KeycloakClientTestMock::USER_UID, $targetId, ['foo' => 'bar']);
        $this->assertEquals(200, $response->getStatusCode(), $response->getContent(false));
        $this->assertSame(['foo' => 'bar'], $response->toArray());

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'GET', '/targets/'.$targetId.'/data');
        $this->assertEquals(200, $response->getStatusCode(), $response->getContent(false));
    }

    public function testEditDataWithAnonymousUser(): void
    {
        $targetId = $this->getOrCreateDefaultTarget()->getId();

        $response = $this->request(null, 'GET', '/targets/'.$targetId.'/data');
        $this->assertEquals(401, $response->getStatusCode());
        $response = $this->putData(null, $targetId, ['foo' => 'bar']);
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testDataOfUnknownTarget(): void
    {
        $response = $this->putData(KeycloakClientTestMock::ADMIN_UID, 'c705d014-5e18-4711-bad6-5e9e27e10099', ['foo' => 'bar']);
        $this->assertEquals(404, $response->getStatusCode());
    }

    private function putData(?string $userId, string $targetId, ?array $data): ResponseInterface
    {
        return $this->request(
            null !== $userId ? KeycloakClientTestMock::getJwtFor($userId) : null,
            'PUT',
            '/targets/'.$targetId.'/data',
            ['data' => $data],
        );
    }
}
