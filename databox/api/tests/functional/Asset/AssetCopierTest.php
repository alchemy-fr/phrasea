<?php

declare(strict_types=1);

namespace App\Tests\Functional\Asset;

use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Service\Asset\AssetCopier;
use App\Tests\Functional\AbstractDataboxTestCase;
use App\Tests\Functional\Api\Rendition\RenditionTestTrait;

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

    private function copy(Asset $asset, array $allowedRenditions, array $options = []): Asset
    {
        $em = self::getEntityManager();
        $em->flush();
        $em->clear();

        $asset = $em->find(Asset::class, $asset->getId());
        $workspace = $asset->getWorkspace();

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
