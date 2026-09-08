<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\RenditionPolicy;
use App\Model\AssetTypeEnum;
use App\Tests\AbstractDataboxTestCase;

/**
 * The rendition scope of an attribute definition must survive a round-trip through the API:
 * sent as IRIs, stored, and returned as IRIs the client can feed back into the form.
 */
class AttributeDefinitionWriteMetadataRenditionsTest extends AbstractDataboxTestCase
{
    public function testTheRenditionScopeSurvivesARoundTrip(): void
    {
        $workspace = $this->getOrCreateDefaultWorkspace();
        $main = $this->createRenditionDefinition('Main');
        $thumbnail = $this->createRenditionDefinition('Thumbnail');
        $definition = $this->createAttributeDefinition([
            'name' => 'Credit',
            'workspace' => $workspace,
        ]);

        $client = static::createClient();
        $mainIri = '/rendition-definitions/'.$main->getId();
        $thumbnailIri = '/rendition-definitions/'.$thumbnail->getId();

        $response = $client->request('PUT', '/attribute-definitions/'.$definition->getId(), [
            'headers' => $this->getAuthHeaders(),
            'json' => [
                'writeMetadata' => ['IPTC:Credit'],
                'writeMetadataRenditions' => [$mainIri, $thumbnailIri],
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertEqualsCanonicalizing(
            [$mainIri, $thumbnailIri],
            $response->toArray()['writeMetadataRenditions'],
            'the scope must come back as IRIs',
        );

        // and it must still be there when the definition is fetched again
        static::getEntityManager()->clear();
        $response = $client->request('GET', '/attribute-definitions/'.$definition->getId(), [
            'headers' => $this->getAuthHeaders(),
        ]);

        $this->assertEqualsCanonicalizing(
            [$mainIri, $thumbnailIri],
            $response->toArray()['writeMetadataRenditions'],
        );
    }

    public function testTheScopeCanBeNarrowedAndCleared(): void
    {
        $workspace = $this->getOrCreateDefaultWorkspace();
        $main = $this->createRenditionDefinition('Main');
        $thumbnail = $this->createRenditionDefinition('Thumbnail');
        $definition = $this->createAttributeDefinition([
            'name' => 'Credit',
            'workspace' => $workspace,
        ]);
        $definition->setWriteMetadataRenditions([$main, $thumbnail]);
        static::getEntityManager()->persist($definition);
        static::getEntityManager()->flush();

        $client = static::createClient();
        $mainIri = '/rendition-definitions/'.$main->getId();

        $response = $client->request('PUT', '/attribute-definitions/'.$definition->getId(), [
            'headers' => $this->getAuthHeaders(),
            'json' => ['writeMetadataRenditions' => [$mainIri]],
        ]);
        $this->assertSame([$mainIri], $response->toArray()['writeMetadataRenditions']);

        static::getEntityManager()->clear();
        $response = $client->request('PUT', '/attribute-definitions/'.$definition->getId(), [
            'headers' => $this->getAuthHeaders(),
            'json' => ['writeMetadataRenditions' => []],
        ]);
        $this->assertSame([], $response->toArray()['writeMetadataRenditions'], 'an empty scope means all renditions');
    }

    /**
     * @return array<string, string>
     */
    private function getAuthHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
        ];
    }

    private function createRenditionDefinition(string $name): RenditionDefinition
    {
        $em = static::getEntityManager();
        $workspace = $this->getOrCreateDefaultWorkspace();

        $policy = new RenditionPolicy();
        $policy->setWorkspace($workspace);
        $policy->setName($name.' policy');
        $policy->setEditable(true);
        $policy->setPublic(true);
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
