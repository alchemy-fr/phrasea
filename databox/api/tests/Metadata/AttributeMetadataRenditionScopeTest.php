<?php

declare(strict_types=1);

namespace App\Tests\Metadata;

use App\Attribute\AttributeInterface;
use App\Entity\Core\Asset;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\RenditionPolicy;
use App\Model\AssetTypeEnum;
use App\Service\Asset\Attribute\AttributeMetadataEmbedder;
use App\Tests\AbstractDataboxTestCase;
use PHPExiftool\Driver\Metadata\Metadata;
use PHPExiftool\Driver\Metadata\MetadataBag;

/**
 * An attribute definition can restrict the renditions its value is written into, through
 * AttributeDefinition::$writeMetadataRenditions. An empty scope means every rendition.
 */
class AttributeMetadataRenditionScopeTest extends AbstractDataboxTestCase
{
    public function testAnUnscopedAttributeIsWrittenIntoEveryRendition(): void
    {
        $this->createDefinition('Credit', 'IPTC:Credit', 'CoolMedia');
        $asset = $this->createAssetWithAttributes();

        $this->assertSame(['IPTC:Credit' => ['CoolMedia']], $this->buildBag($asset, $this->createRenditionDefinition('Main')));
        $this->assertSame(['IPTC:Credit' => ['CoolMedia']], $this->buildBag($asset, $this->createRenditionDefinition('Thumbnail')));
    }

    public function testAnUnscopedAttributeIsWrittenIntoDynamicRenditions(): void
    {
        $this->createDefinition('Credit', 'IPTC:Credit', 'CoolMedia');
        $asset = $this->createAssetWithAttributes();

        // a dynamic rendition has no definition
        $this->assertSame(['IPTC:Credit' => ['CoolMedia']], $this->buildBag($asset, null));
    }

    public function testAScopedAttributeOnlyReachesItsRenditions(): void
    {
        $main = $this->createRenditionDefinition('Main');
        $thumbnail = $this->createRenditionDefinition('Thumbnail');

        $definition = $this->createDefinition('Credit', 'IPTC:Credit', 'CoolMedia');
        $definition->setWriteMetadataRenditions([$main]);
        self::getEntityManager()->persist($definition);
        self::getEntityManager()->flush();

        $asset = $this->createAssetWithAttributes();

        $this->assertSame(['IPTC:Credit' => ['CoolMedia']], $this->buildBag($asset, $main));
        $this->assertSame([], $this->buildBag($asset, $thumbnail));
    }

    public function testAScopedAttributeNeverReachesADynamicRendition(): void
    {
        $main = $this->createRenditionDefinition('Main');

        $definition = $this->createDefinition('Credit', 'IPTC:Credit', 'CoolMedia');
        $definition->setWriteMetadataRenditions([$main]);
        self::getEntityManager()->persist($definition);
        self::getEntityManager()->flush();

        $asset = $this->createAssetWithAttributes();

        $this->assertSame([], $this->buildBag($asset, null));
    }

    public function testScopedAndUnscopedAttributesMix(): void
    {
        $main = $this->createRenditionDefinition('Main');
        $thumbnail = $this->createRenditionDefinition('Thumbnail');

        $scoped = $this->createDefinition('Credit', 'IPTC:Credit', 'CoolMedia');
        $scoped->setWriteMetadataRenditions([$main]);
        self::getEntityManager()->persist($scoped);
        $this->createDefinition('City', 'IPTC:City', 'Paris');
        self::getEntityManager()->flush();

        $asset = $this->createAssetWithAttributes();

        $this->assertSame([
            'IPTC:Credit' => ['CoolMedia'],
            'IPTC:City' => ['Paris'],
        ], $this->buildBag($asset, $main));

        $this->assertSame(['IPTC:City' => ['Paris']], $this->buildBag($asset, $thumbnail));
    }

    /**
     * @return array<string, array>
     */
    private function buildBag(Asset $asset, ?RenditionDefinition $renditionDefinition): array
    {
        $asset->attributesIndex = null;

        /** @var AttributeMetadataEmbedder $embedder */
        $embedder = self::getContainer()->get(AttributeMetadataEmbedder::class);

        $byId = [];
        /** @var Metadata $meta */
        foreach ($embedder->buildMetadataBag($asset, $renditionDefinition) ?? new MetadataBag() as $meta) {
            $byId[$meta->getTagGroup()->getId()] = $meta->getValue()->asArray();
        }

        return $byId;
    }

    private function createDefinition(string $name, string $tag, string $fallbackValue): AttributeDefinition
    {
        $definition = $this->createAttributeDefinition([
            'name' => $name,
            'slug' => strtolower($name),
            'fallback' => [AttributeInterface::NO_LOCALE => $fallbackValue],
            'no_flush' => true,
        ]);
        $definition->setWriteMetadata([$tag]);
        self::getEntityManager()->persist($definition);
        self::getEntityManager()->flush();

        return $definition;
    }

    private function createRenditionDefinition(string $name): RenditionDefinition
    {
        $em = self::getEntityManager();
        $workspace = $this->getOrCreateDefaultWorkspace();

        $existing = $em->getRepository(RenditionDefinition::class)->findOneBy([
            'workspace' => $workspace->getId(),
            'name' => $name,
        ]);
        if ($existing instanceof RenditionDefinition) {
            return $existing;
        }

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

    private function createAssetWithAttributes(): Asset
    {
        return $this->createAsset([
            'workspace' => $this->getOrCreateDefaultWorkspace(),
        ]);
    }
}
