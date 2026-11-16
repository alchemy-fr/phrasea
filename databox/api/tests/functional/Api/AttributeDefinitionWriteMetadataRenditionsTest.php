<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\RenditionPolicy;
use App\Model\AssetTypeEnum;
use App\Tests\Functional\AbstractDataboxTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

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

        $response = $client->request('PATCH', '/attribute-definitions/'.$definition->getId(), [
            'headers' => ['Content-Type' => 'application/merge-patch+json'] + $this->getAuthHeaders(),
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

        $response = $client->request('PATCH', '/attribute-definitions/'.$definition->getId(), [
            'headers' => ['Content-Type' => 'application/merge-patch+json'] + $this->getAuthHeaders(),
            'json' => ['writeMetadataRenditions' => [$mainIri]],
        ]);
        $this->assertSame([$mainIri], $response->toArray()['writeMetadataRenditions']);

        static::getEntityManager()->clear();
        $response = $client->request('PATCH', '/attribute-definitions/'.$definition->getId(), [
            'headers' => ['Content-Type' => 'application/merge-patch+json'] + $this->getAuthHeaders(),
            'json' => ['writeMetadataRenditions' => []],
        ]);
        $this->assertSame([], $response->toArray()['writeMetadataRenditions'], 'an empty scope means all renditions');
    }

    public function testADescriptiveTagCanBeWritten(): void
    {
        $definition = $this->createAttributeDefinition([
            'name' => 'Title',
            'workspace' => $this->getOrCreateDefaultWorkspace(),
        ]);

        $response = static::createClient()->request('PATCH', '/attribute-definitions/'.$definition->getId(), [
            'headers' => ['Content-Type' => 'application/merge-patch+json'] + $this->getAuthHeaders(),
            'json' => ['writeMetadata' => ['XMP-dc:Title', 'IPTC:Headline']],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSame(['XMP-dc:Title', 'IPTC:Headline'], $response->toArray()['writeMetadata']);
    }

    #[DataProvider('provideRefusedTags')]
    public function testATagActingOnTheFileSystemIsRefused(string $tag, string $expectedMessage): void
    {
        $definition = $this->createAttributeDefinition([
            'name' => 'Title',
            'workspace' => $this->getOrCreateDefaultWorkspace(),
        ]);

        $response = static::createClient()->request('PATCH', '/attribute-definitions/'.$definition->getId(), [
            'headers' => ['Content-Type' => 'application/merge-patch+json'] + $this->getAuthHeaders(),
            'json' => ['writeMetadata' => ['XMP-dc:Title', $tag]],
        ]);

        $this->assertResponseStatusCodeSame(400);
        $this->assertStringContainsString($expectedMessage, $response->getContent(false));

        static::getEntityManager()->clear();
        $this->assertNull(
            static::getEntityManager()->find(AttributeDefinition::class, $definition->getId())->getWriteMetadata(),
        );
    }

    public static function provideRefusedTags(): iterable
    {
        yield 'hard link' => ['File:HardLink', 'File:HardLink'];
        yield 'symbolic link' => ['File:SymLink', 'File:SymLink'];
        // phpexiftool upper-cases the first letter of the group: the check is made on the canonical id
        yield 'lower-cased System group' => ['system:Directory', 'System:Directory'];
        // Unknown to phpexiftool or refused once canonicalized, depending on its loaded dictionary
        yield 'upper-cased System group' => ['SYSTEM:FileName', ':FileName'];
        yield 'composite' => ['Composite:GPSPosition', 'Composite:GPSPosition'];
        yield 'unknown tag' => ['Unknown:Tag', 'Unknown metadata tag'];
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
