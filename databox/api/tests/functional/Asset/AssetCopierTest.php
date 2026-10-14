<?php

declare(strict_types=1);

namespace App\Tests\Functional\Asset;

use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\Collection;
use App\Entity\Core\CollectionAsset;
use App\Service\Asset\AssetCopier;
use App\Tests\Functional\AbstractDataboxTestCase;
use App\Tests\Functional\Api\Rendition\RenditionTestTrait;
use Doctrine\ORM\Events;

final class AssetCopierTest extends AbstractDataboxTestCase
{
    use RenditionTestTrait;

    private const string BUILD_DEFINITION = "image:\n    transformations: []\n";

    public function testCopyOnlyAllowedRenditions(): void
    {
        $workspace = $this->getOrCreateDefaultWorkspace();
        $policy = $this->createRenditionPolicy($workspace, 'Copy policy');
        $allowed = $this->createRenditionDefinition($workspace, $policy, 'allowed');
        $denied = $this->createRenditionDefinition($workspace, $policy, 'denied');

        $asset = $this->createAsset(['workspace' => $workspace]);
        $asset->setSource($this->createUrlFile($workspace));
        $allowedFile = $this->createUrlFile($workspace, 'https://cdn.example.com/allowed.jpg');
        $this->createAssetRendition($asset, $allowed, $allowedFile);
        $this->createAssetRendition($asset, $denied, $this->createUrlFile($workspace, 'https://cdn.example.com/denied.jpg'));
        $this->createDynamicRendition($asset, 'crop');

        $copy = $this->copy($asset, [$allowed->getId()]);

        $renditions = $this->getRenditionsByName($copy);
        $this->assertEqualsCanonicalizing(['allowed', 'crop'], array_keys($renditions));
        $this->assertSame('https://cdn.example.com/allowed.jpg', $renditions['allowed']->getFile()->getPath());
        $this->assertNotSame($allowedFile->getId(), $renditions['allowed']->getFile()->getId());
    }

    public function testCopyDynamicRenditionsBuiltFromCopiedRenditions(): void
    {
        $workspace = $this->getOrCreateDefaultWorkspace();
        $policy = $this->createRenditionPolicy($workspace, 'Copy policy');
        $allowed = $this->createRenditionDefinition($workspace, $policy, 'allowed');
        $denied = $this->createRenditionDefinition($workspace, $policy, 'denied');

        $asset = $this->createAsset(['workspace' => $workspace]);
        $allowedRendition = $this->createAssetRendition($asset, $allowed, $this->createUrlFile($workspace));
        $deniedRendition = $this->createAssetRendition($asset, $denied, $this->createUrlFile($workspace));
        $fromAllowed = $this->createDynamicRendition($asset, 'from-allowed', [
            AssetRendition::OPTION_WRITE_METADATA => true,
            AssetRendition::OPTION_SOURCE_RENDITION_ID => $allowedRendition->getId(),
        ]);
        $this->createDynamicRendition($asset, 'from-dynamic', [
            AssetRendition::OPTION_SOURCE_RENDITION_ID => $fromAllowed->getId(),
        ]);
        $fromDenied = $this->createDynamicRendition($asset, 'from-denied', [
            AssetRendition::OPTION_SOURCE_RENDITION_ID => $deniedRendition->getId(),
        ]);
        $this->createDynamicRendition($asset, 'from-dropped', [
            AssetRendition::OPTION_SOURCE_RENDITION_ID => $fromDenied->getId(),
        ]);

        $copy = $this->copy($asset, [$allowed->getId()]);

        $renditions = $this->getRenditionsByName($copy);
        $this->assertEqualsCanonicalizing(['allowed', 'from-allowed', 'from-dynamic'], array_keys($renditions));
        $this->assertSame($renditions['allowed']->getId(), $renditions['from-allowed']->getSourceRenditionId());
        $this->assertTrue($renditions['from-allowed']->isWriteMetadata());
        $this->assertSame($renditions['from-allowed']->getId(), $renditions['from-dynamic']->getSourceRenditionId());
    }

    public function testRetryAfterFailureCopiesFilesAgain(): void
    {
        $workspace = $this->getOrCreateDefaultWorkspace();
        $asset = $this->createAsset(['workspace' => $workspace]);
        $asset->setSource($this->createUrlFile($workspace));

        $em = self::getEntityManager();
        $em->flush();
        $em->clear();

        /** @var AssetCopier $copier */
        $copier = self::getService(AssetCopier::class);
        $failingListener = new class {
            public function onFlush(): void
            {
                throw new \RuntimeException('Flush failure');
            }
        };

        $em->getEventManager()->addEventListener(Events::onFlush, $failingListener);
        try {
            $asset = $em->find(Asset::class, $asset->getId());
            $copier->copyAsset('copier_user', $asset, $asset->getWorkspace(), null, []);
            $this->fail('The copy should have failed');
        } catch (\RuntimeException $e) {
            $this->assertSame('Flush failure', $e->getMessage());
        } finally {
            $em->getEventManager()->removeEventListener(Events::onFlush, $failingListener);
        }

        // As the messenger worker does after a failed message
        $em->clear();

        $copy = $this->copy($asset, []);
        $this->assertNotNull($copy->getSource());
        $this->assertNotSame($asset->getSource()->getId(), $copy->getSource()->getId());
    }

    public function testCopyIntoCollectionSetsReferenceCollection(): void
    {
        $workspace = $this->getOrCreateDefaultWorkspace();
        $source = $this->createCollection(['workspace' => $workspace]);
        $destination = $this->createCollection(['workspace' => $workspace]);
        $asset = $this->createAsset(['workspace' => $workspace, 'collectionId' => $source->getId()]);

        $copy = $this->copy($asset, [], ['collection' => $destination]);

        $this->assertSame($destination->getId(), $copy->getReferenceCollectionId());
        $this->assertSame([$destination->getId()], $copy->getCollections()->map(fn (CollectionAsset $ca): string => $ca->getCollection()->getId())->getValues());
    }

    private function copy(Asset $asset, array $allowedRenditions, array $options = []): Asset
    {
        $em = self::getEntityManager();
        $em->flush();
        $em->clear();

        $asset = $em->find(Asset::class, $asset->getId());
        $workspace = $asset->getWorkspace();
        if (isset($options['collection'])) {
            $options['collection'] = $em->find(Collection::class, $options['collection']->getId());
        }

        /** @var AssetCopier $copier */
        $copier = self::getService(AssetCopier::class);
        $copier->copyAsset('copier_user', $asset, $workspace, $options['collection'] ?? null, $allowedRenditions, $options);
        $em->clear();

        $copy = $em->getRepository(Asset::class)->findOneBy(['ownerId' => 'copier_user']);
        $this->assertInstanceOf(Asset::class, $copy);

        return $copy;
    }

    private function createDynamicRendition(Asset $asset, string $name, array $buildOptions = []): AssetRendition
    {
        $em = self::getEntityManager();

        $rendition = new AssetRendition();
        $rendition->setAsset($asset);
        $rendition->setName($name);
        $rendition->setBuildDefinition(self::BUILD_DEFINITION);
        $rendition->setBuildOptions($buildOptions ?: null);
        $em->persist($rendition);
        $em->flush();

        return $rendition;
    }

    /**
     * @return array<string, AssetRendition>
     */
    private function getRenditionsByName(Asset $asset): array
    {
        $renditions = self::getEntityManager()->getRepository(AssetRendition::class)->findBy(['asset' => $asset->getId()]);

        return array_combine(array_map(fn (AssetRendition $r): string => $r->getName(), $renditions), $renditions);
    }
}
