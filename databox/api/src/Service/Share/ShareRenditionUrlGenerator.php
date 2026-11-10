<?php

declare(strict_types=1);

namespace App\Service\Share;

use ApiPlatform\Metadata\UrlGeneratorInterface;
use App\Api\Provider\ShareReadProvider;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\Share;
use App\Repository\Core\AssetRenditionRepository;
use App\Service\Asset\AssetPolicy\AssetPolicyManager;
use Doctrine\ORM\EntityManagerInterface;

/**
 * The public URLs (permalinks) a share link gives to the renditions of an asset.
 *
 * Absolute URLs: outside a request (e.g. in a worker), the router context comes
 * from DATABOX_API_URL.
 */
final readonly class ShareRenditionUrlGenerator
{
    public function __construct(
        private EntityManagerInterface $em,
        private UrlGeneratorInterface $urlGenerator,
        private AssetPolicyManager $assetPolicyManager,
    ) {
    }

    /**
     * The renditions of the asset the share link delivers, files loaded
     * (same rules as ShareRenditionProvider).
     *
     * @return AssetRendition[]
     */
    public function getSharedRenditions(Share $share, Asset $asset): array
    {
        /** @var AssetRenditionRepository $repository */
        $repository = $this->em->getRepository(AssetRendition::class);
        $renditions = $repository->findAssetRenditions($asset->getId(), [
            AssetRenditionRepository::OPT_WITH_FILE => true,
            AssetRenditionRepository::OPT_EXCLUDE_DEFINITIONS => $this->assetPolicyManager->getPolicyApplicationFilter($asset)->getFilteredRenditions(),
        ]);

        return array_values(array_filter(
            $renditions,
            fn (AssetRendition $rendition): bool => ShareReadProvider::isRenditionShared($share, $rendition),
        ));
    }

    public function generateUrl(Share $share, Asset $asset, RenditionDefinition $definition): string
    {
        return $this->urlGenerator->generate('share_public_rendition', [
            'id' => $share->getId(),
            'asset' => $asset->getId(),
            'rendition' => $definition->getId(),
            'token' => $share->getToken(),
        ], UrlGeneratorInterface::ABS_URL);
    }

    /**
     * @return array<string, string> the URLs of the shared renditions, indexed by definition name
     */
    public function getRenditionUrls(Share $share, Asset $asset): array
    {
        $urls = [];
        foreach ($this->getSharedRenditions($share, $asset) as $rendition) {
            $definition = $rendition->getDefinition();
            $urls[$definition->getName()] ??= $this->generateUrl($share, $asset, $definition);
        }

        return $urls;
    }
}
