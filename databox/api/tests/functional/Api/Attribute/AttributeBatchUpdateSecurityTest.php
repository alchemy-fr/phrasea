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

    public function testOAuthClientNeedsTheEditScopeToReplaceInLockedPolicies(): void
    {
        $client = static::createClient();
        $this->createMemberWorkspace();
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
        $json = ['actions' => [['action' => 'replace', 'value' => 'All', 'replaceWith' => 'No']]];

        $this->sendAsClient($client, $uri, 'asset:edit attribute-definition:read', $json);
        $this->assertResponseIsSuccessful();
        self::getEntityManager()->clear();
        $this->assertSame('All rights reserved', $this->getAttributeOf($asset)->getValue());

        $this->sendAsClient($client, $uri, 'asset:edit attribute-definition:edit', $json);
        $this->assertResponseIsSuccessful();
        self::getEntityManager()->clear();
        $this->assertSame('No rights reserved', $this->getAttributeOf($asset)->getValue());
    }

    public function testEntityOfAnotherWorkspaceIsRefused(): void
    {
        $client = static::createClient();
        $workspace = $this->createMemberWorkspace();
        $otherWorkspace = $this->createWorkspace(['ownerId' => self::ADMIN, 'slug' => 'other-workspace']);
        $definitionId = $this->getOrCreateTagsDefinition($workspace)->getId();
        $tag = $this->findOrCreateTagEntity('Approved', $workspace);
        $foreignTag = $this->findOrCreateTagEntity('Secret', $otherWorkspace);
        $asset = $this->createAsset(['ownerId' => self::USER]);
        $uri = '/assets/'.$asset->getId().'/attributes';

        $refusedActions = [
            ['action' => 'add', 'definitionId' => $definitionId, 'value' => $foreignTag->getId()],
            ['action' => 'add', 'definitionId' => $definitionId, 'value' => '6f1c2f0e-5b3a-4c1e-9d6a-0b8e2a7c4d11'],
            ['action' => 'add', 'definitionId' => $definitionId, 'value' => 'not-a-uuid'],
            ['action' => 'add', 'definitionId' => $this->getOrCreateTagsDefinition($otherWorkspace)->getId(), 'value' => $foreignTag->getId()],
        ];
        foreach ($refusedActions as $action) {
            $this->send($client, 'POST', $uri, self::USER, ['actions' => [$action]]);
            $this->assertGreaterThanOrEqual(400, $client->getResponse()->getStatusCode(), json_encode($action, JSON_THROW_ON_ERROR));
            $this->assertLessThan(500, $client->getResponse()->getStatusCode(), json_encode($action, JSON_THROW_ON_ERROR));
        }

        self::getEntityManager()->clear();
        $this->assertSame([], $this->getTagValues($asset));

        $this->send($client, 'POST', $uri, self::USER, [
            'actions' => [['action' => 'add', 'definitionId' => $definitionId, 'value' => $tag->getId()]],
        ]);
        $this->assertResponseIsSuccessful();

        self::getEntityManager()->clear();
        $this->assertSame([$tag->getId()], $this->getTagValues($asset));
    }

    public function testDeleteRemovesTheGivenValuesOnly(): void
    {
        $client = static::createClient();
        $workspace = $this->createMemberWorkspace();
        $definitionId = $this->getOrCreateTagsDefinition()->getId();
        $keep = $this->findOrCreateTagEntity('Keep');
        $remove = $this->findOrCreateTagEntity('Remove');
        $asset = $this->createAsset(['ownerId' => self::USER, 'tags' => ['Keep', 'Remove']]);
        $other = $this->createAsset(['ownerId' => self::USER, 'tags' => ['Remove']]);

        // No value to remove: nothing is removed
        $this->send($client, 'POST', '/attributes/batch-update', self::USER, [
            'workspaceId' => $workspace->getId(),
            'assets' => [$asset->getId(), $other->getId()],
            'actions' => [['action' => 'delete', 'definitionId' => $definitionId, 'value' => []]],
        ]);
        $this->assertResponseIsSuccessful();
        self::getEntityManager()->clear();
        $this->assertEqualsCanonicalizing([$keep->getId(), $remove->getId()], $this->getTagValues($asset));

        $this->send($client, 'POST', '/attributes/batch-update', self::USER, [
            'workspaceId' => $workspace->getId(),
            'assets' => [$asset->getId(), $other->getId()],
            'actions' => [['action' => 'delete', 'definitionId' => $definitionId, 'value' => [$remove->getId(), 'unknown']]],
        ]);
        $this->assertResponseIsSuccessful();
        self::getEntityManager()->clear();
        $this->assertSame([$keep->getId()], $this->getTagValues($asset));
        $this->assertSame([], $this->getTagValues($other));

        // A single value
        $this->send($client, 'POST', '/assets/'.$asset->getId().'/attributes', self::USER, [
            'actions' => [['action' => 'delete', 'definitionId' => $definitionId, 'value' => $keep->getId()]],
        ]);
        $this->assertResponseIsSuccessful();
        self::getEntityManager()->clear();
        $this->assertSame([], $this->getTagValues($asset));
    }

    /**
     * @return string[]
     */
    private function getTagValues(Asset $asset): array
    {
        return array_map(
            static fn (Attribute $attribute): string => $attribute->getValue(),
            self::getEntityManager()->getRepository(Attribute::class)->findBy([
                'asset' => $asset->getId(),
                'definition' => $this->getOrCreateTagsDefinition($asset->getWorkspace())->getId(),
            ]),
        );
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

    private function sendAsClient(Client $client, string $uri, string $scope, array $json): void
    {
        $client->request('POST', $uri, [
            'headers' => ['Authorization' => 'Bearer '.KeycloakClientTestMock::getClientCredentialJwt($scope)],
            'json' => $json,
        ]);
    }

    private function send(Client $client, string $method, string $uri, string $userId, array $json): void
    {
        $client->request($method, $uri, [
            'headers' => ['Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor($userId)],
            'json' => $json,
        ]);
    }
}
