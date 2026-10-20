<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Core\AttributePolicy;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\RenditionPolicy;
use App\Entity\Core\Workspace;
use App\Model\AssetTypeEnum;
use App\Tests\AbstractSearchTestCase;

class RenditionDefinitionTest extends AbstractSearchTestCase
{
    public function testUpdateRenditionDefinition(): void
    {
        self::enableFixtures();
        $client = static::createClient();
        $iri = $this->findIriBy(RenditionDefinition::class, ['name' => 'preview']);

        $client->request('PUT', $iri, [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'useAsThumbnail' => true,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@id' => $iri,
            'useAsThumbnail' => true,
        ]);
    }

    public function testUpdateRenditionDefinitionMetadata(): void
    {
        self::enableFixtures();
        $client = static::createClient();
        $iri = $this->findIriBy(RenditionDefinition::class, ['name' => 'preview']);

        $client->request('PUT', $iri, [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'metadata' => [
                    'IPTC:CopyrightNotice' => '© Alchemy',
                ],
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@id' => $iri,
            'metadata' => [
                'IPTC:CopyrightNotice' => '© Alchemy',
            ],
        ]);

        $client->request('PUT', $iri, [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'metadata' => [
                    'NotANamespacedTag' => 'foo',
                ],
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);

        $response = $client->request('PUT', $iri, [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'metadata' => [
                    'Unknown:Tag' => 'foo',
                ],
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertStringContainsString('Unknown metadata tag', $response->getContent(false));
    }

    public function testGetRenditionDefinition(): void
    {
        self::enableFixtures();
        $client = static::createClient();
        $iri = $this->findIriBy(RenditionDefinition::class, ['name' => 'preview']);

        $response = $client->request('GET', $iri, [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            ],
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@id' => $iri,
        ]);
        $this->assertArrayNotHasKey('useAsThumbnail', $response->toArray());

        $response = $client->request('GET', $iri, [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getClientCredentialJwt(),
            ],
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertArrayNotHasKey('useAsThumbnail', $response->toArray());

        $client->request('GET', $iri, [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getClientCredentialJwt('rendition-definition:read'),
            ],
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@id' => $iri,
            'useAsThumbnail' => false,
        ]);
    }

    public function testListRenditionDefinitionsOfPrivatePolicy(): void
    {
        $workspace = $this->createWorkspace([
            'ownerId' => KeycloakClientTestMock::USER_UID,
        ]);
        $this->createPolicyWithDefinition($workspace, 'public-rendition', true);
        $private = $this->createPolicyWithDefinition($workspace, 'private-rendition', false);

        $this->assertListedDefinitions($workspace, KeycloakClientTestMock::USER_UID, [
            'public-rendition' => true,
            'private-rendition' => false,
        ]);

        self::getPermissionManager()->updateOrCreateAce(
            AccessControlEntryInterface::TYPE_USER_VALUE,
            KeycloakClientTestMock::USER_UID,
            AttributePolicy::OBJECT_TYPE,
            $private->getPolicy()->getId(),
            PermissionInterface::VIEW,
        );

        $this->assertListedDefinitions($workspace, KeycloakClientTestMock::USER_UID, [
            'public-rendition' => true,
            'private-rendition' => false,
        ]);

        $this->grantUserOnObject(KeycloakClientTestMock::USER_UID, $private->getPolicy(), PermissionInterface::CHILD_VIEW);

        $this->assertListedDefinitions($workspace, KeycloakClientTestMock::USER_UID, [
            'public-rendition' => true,
            'private-rendition' => true,
        ]);

        $this->addUserOnWorkspace(KeycloakClientTestMock::OTHER_USER_UID, $workspace->getId());
        $this->assertListedDefinitions($workspace, KeycloakClientTestMock::OTHER_USER_UID, [
            'public-rendition' => true,
            'private-rendition' => false,
        ]);
    }

    /**
     * @param array<string, bool> $expected
     */
    private function assertListedDefinitions(Workspace $workspace, string $userId, array $expected): void
    {
        $response = static::createClient()->request('GET', '/rendition-definitions', [
            'query' => [
                'workspaceId' => $workspace->getId(),
            ],
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor($userId),
            ],
        ]);
        $this->assertResponseIsSuccessful();

        $names = array_map(static fn (array $d): string => $d['name'], $response->toArray()['hydra:member']);
        foreach ($expected as $name => $listed) {
            $this->assertSame($listed, in_array($name, $names, true), sprintf('Rendition "%s" should%s be listed', $name, $listed ? '' : ' not'));
        }
    }

    private function createPolicyWithDefinition(Workspace $workspace, string $name, bool $public): RenditionDefinition
    {
        $em = self::getEntityManager();

        $policy = new RenditionPolicy();
        $policy->setWorkspace($workspace);
        $policy->setName($name.' policy');
        $policy->setPublic($public);
        $policy->setEditable(false);
        $em->persist($policy);

        $definition = new RenditionDefinition();
        $definition->setWorkspace($workspace);
        $definition->setPolicy($policy);
        $definition->setName($name);
        $definition->setTarget(AssetTypeEnum::Both);
        $definition->setBuildMode(RenditionDefinition::BUILD_MODE_PICK_SOURCE);
        $em->persist($definition);
        $em->flush();

        return $definition;
    }
}
