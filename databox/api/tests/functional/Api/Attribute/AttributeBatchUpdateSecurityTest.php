<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Attribute;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use ApiPlatform\Test\Client;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\Workspace;
use App\Tests\Functional\AbstractDataboxTestCase;

/**
 * A batch update only reaches the attributes of the assets it edits,
 * and checks the attribute policy of every action.
 */
class AttributeBatchUpdateSecurityTest extends AbstractDataboxTestCase
{
    private const string ADMIN = KeycloakClientTestMock::ADMIN_UID;
    private const string USER = KeycloakClientTestMock::USER_UID;

    public function testBatchUpdateCannotReachTheAttributeOfAnotherAsset(): void
    {
        $client = static::createClient();
        $this->createMemberWorkspace();
        $definition = $this->createAttributeDefinition(['name' => 'Title', 'slug' => 'title']);

        $mine = $this->createAsset(['ownerId' => self::USER]);
        $victim = $this->createAsset([
            'ownerId' => self::ADMIN,
            'attributes' => [['definition' => $definition, 'value' => 'Original']],
        ]);
        $victimAttributeId = $this->getAttributeOf($victim)->getId();

        foreach (['pwned', null] as $value) {
            $this->send($client, 'POST', '/assets/'.$mine->getId().'/attributes', self::USER, [
                'actions' => [['action' => 'set', 'id' => $victimAttributeId, 'value' => $value]],
            ]);
            $this->assertResponseStatusCodeSame(400);
        }

        self::getEntityManager()->clear();
        $this->assertSame('Original', $this->getAttributeOf($victim)->getValue());
    }

    public function testBatchUpdateHonoursTheAttributePolicyOfEveryAction(): void
    {
        $client = static::createClient();
        $this->createMemberWorkspace();
        $public = $this->createAttributeDefinition(['name' => 'Title', 'slug' => 'title']);
        $locked = $this->createAttributeDefinition([
            'name' => 'Rights',
            'slug' => 'rights',
            'policy' => $this->createAttributePolicy(['name' => 'Locked', 'editable' => false]),
        ]);
        $asset = $this->createAsset([
            'ownerId' => self::USER,
            'attributes' => [['definition' => $locked, 'value' => 'All rights reserved']],
        ]);
        $uri = '/assets/'.$asset->getId().'/attributes';

        $this->send($client, 'POST', $uri, self::USER, [
            'actions' => [['action' => 'delete', 'definitionId' => $locked->getId()]],
        ]);
        $this->assertResponseStatusCodeSame(403);

        $this->send($client, 'POST', $uri, self::USER, [
            'actions' => [['action' => 'replace', 'definitionId' => $locked->getId(), 'value' => 'All', 'replaceWith' => 'No']],
        ]);
        $this->assertResponseStatusCodeSame(403);

        // The validated definition must be the written one
        $this->send($client, 'POST', $uri, self::USER, [
            'actions' => [[
                'action' => 'set',
                'definitionId' => $locked->getId(),
                'definition' => '/attribute-definitions/'.$public->getId(),
                'value' => 'Public domain',
            ]],
        ]);
        $this->assertResponseStatusCodeSame(400);

        self::getEntityManager()->clear();
        $this->assertSame('All rights reserved', $this->getAttributeOf($asset)->getValue());
    }

    /**
     * A workspace of the admin, the user being one of its readers (and more if $extraPermissions).
     */
    private function createMemberWorkspace(int $extraPermissions = 0): Workspace
    {
        $workspace = $this->getOrCreateDefaultWorkspace(['ownerId' => self::ADMIN]);
        $this->grantUserOnObject(self::USER, $workspace, PermissionInterface::VIEW | $extraPermissions);

        return $workspace;
    }

    private function getAttributeOf(Asset $asset): Attribute
    {
        return self::getEntityManager()->getRepository(Attribute::class)->findOneBy(['asset' => $asset->getId()]);
    }

    private function send(Client $client, string $method, string $uri, string $userId, array $json): void
    {
        $client->request($method, $uri, [
            'headers' => ['Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor($userId)],
            'json' => $json,
        ]);
    }
}
