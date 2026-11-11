<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AclBundle\Security\PermissionManager;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Target;
use App\Security\Acl\FormSchemaAclObject;
use Symfony\Contracts\HttpClient\ResponseInterface;

class TargetFormTest extends AbstractUploaderTestCase
{
    public static function definition(): array
    {
        return [
            'version' => 1,
            'workspaceId' => null,
            'fields' => [
                ['id' => 'f_title', 'source' => 'custom', 'key' => 'title', 'type' => 'text', 'required' => true, 'label' => ['_' => 'Title', 'fr' => 'Titre']],
                ['id' => 'f_notify', 'source' => 'builtin', 'key' => '__notify', 'type' => 'boolean', 'label' => ['_' => 'Notify me']],
            ],
        ];
    }

    public function testGetForm(): void
    {
        $targetId = $this->getOrCreateDefaultTarget()->getId();

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'GET', '/targets/'.$targetId.'/form');
        $this->assertEquals(200, $response->getStatusCode(), $response->getContent(false));
        $this->assertSame('null', $response->getContent());

        $target = $this->getOrCreateDefaultTarget();
        $target->setForm(self::definition());
        $em = self::getEntityManager();
        $em->persist($target);
        $em->flush();

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'GET', '/targets/'.$targetId.'/form');
        $this->assertEquals(200, $response->getStatusCode(), $response->getContent(false));
        $this->assertSame(self::definition(), $response->toArray());

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'GET', '/targets/'.$targetId);
        $this->assertEquals(200, $response->getStatusCode(), $response->getContent(false));
        $this->assertTrue($response->toArray()['hasForm']);
        $this->assertFalse($response->toArray()['hasData']);
        $this->assertArrayNotHasKey('form', $response->toArray());
    }

    public function testGetFormOfUnknownTarget(): void
    {
        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'GET', '/targets/c705d014-5e18-4711-bad6-5e9e27e10099/form');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testGetFormOfATargetTheUserCannotRead(): void
    {
        $target = new Target();
        $target->setName('Restricted');
        $target->setSlug('restricted');
        $target->setAllowedGroups(['a_group_the_user_is_not_in']);
        $target->setForm(self::definition());
        $em = self::getEntityManager();
        $em->persist($target);
        $em->flush();

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'GET', '/targets/'.$target->getId().'/form');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testGetFormWithAnonymousUser(): void
    {
        $response = $this->request(null, 'GET', '/targets/'.$this->getOrCreateDefaultTarget()->getId().'/form');
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testPutFormAsAdmin(): void
    {
        $target = $this->getOrCreateDefaultTarget();

        $response = $this->putForm(KeycloakClientTestMock::ADMIN_UID, $target->getId(), self::definition());
        $this->assertEquals(200, $response->getStatusCode(), $response->getContent(false));
        $this->assertSame(self::definition(), $response->toArray());

        $em = self::getEntityManager();
        $em->clear();
        $this->assertSame(self::definition(), $em->find(Target::class, $target->getId())->getForm());

        $response = $this->putForm(KeycloakClientTestMock::ADMIN_UID, $target->getId(), null);
        $this->assertEquals(200, $response->getStatusCode(), $response->getContent(false));
        $this->assertSame('null', $response->getContent());

        $em->clear();
        $this->assertNull($em->find(Target::class, $target->getId())->getForm());
    }

    public function testPutFormWithTheGlobalPermission(): void
    {
        $target = $this->getOrCreateDefaultTarget();

        $response = $this->putForm(KeycloakClientTestMock::USER_UID, $target->getId(), self::definition());
        $this->assertEquals(403, $response->getStatusCode());

        self::getContainer()->get(PermissionManager::class)->updateOrCreateAce(
            AccessControlEntryInterface::TYPE_USER_VALUE,
            KeycloakClientTestMock::USER_UID,
            FormSchemaAclObject::OBJECT_KEY,
            null,
            PermissionInterface::EDIT,
        );

        $response = $this->putForm(KeycloakClientTestMock::USER_UID, $target->getId(), self::definition());
        $this->assertEquals(200, $response->getStatusCode(), $response->getContent(false));
        $this->assertSame(self::definition(), $response->toArray());
    }

    public function testPutFormWithAnonymousUser(): void
    {
        $response = $this->putForm(null, $this->getOrCreateDefaultTarget()->getId(), self::definition());
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testPutFormOfUnknownTarget(): void
    {
        $response = $this->putForm(KeycloakClientTestMock::ADMIN_UID, 'c705d014-5e18-4711-bad6-5e9e27e10099', self::definition());
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testPutInvalidForm(): void
    {
        $definition = self::definition();
        $definition['fields'][0]['key'] = 'not a key';

        $response = $this->putForm(KeycloakClientTestMock::ADMIN_UID, $this->getOrCreateDefaultTarget()->getId(), $definition);
        $this->assertEquals(422, $response->getStatusCode(), $response->getContent(false));
        $this->assertSame('form.fields.0.key', $response->toArray(false)['violations'][0]['propertyPath']);

        $definition = self::definition();
        $definition['fields'][] = ['id' => 'f_tags', 'source' => 'builtin', 'key' => 'databox_tags'];

        $response = $this->putForm(KeycloakClientTestMock::ADMIN_UID, $this->getOrCreateDefaultTarget()->getId(), $definition);
        $this->assertEquals(422, $response->getStatusCode(), $response->getContent(false));
        $this->assertSame('form.workspaceId', $response->toArray(false)['violations'][0]['propertyPath']);
    }

    private function putForm(?string $userId, string $targetId, ?array $form): ResponseInterface
    {
        return $this->request(
            null !== $userId ? KeycloakClientTestMock::getJwtFor($userId) : null,
            'PUT',
            '/targets/'.$targetId.'/form',
            ['form' => $form],
        );
    }
}
