<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Api\Model\Output\AssetDuplicateOutput;
use App\Entity\Core\Asset;
use App\Repository\Core\AssetRepository;
use App\Security\Voter\AbstractVoter;
use App\Service\Asset\FileUrlResolver;
use App\Service\Storage\RenditionManager;

/**
 * Resolves the duplicate file IDs recorded in a quarantined asset's file
 * analysis into the actual duplicate assets, so the client "merge" flow can
 * display them and let the user pick which one to keep.
 */
final class AssetDuplicatesProvider implements ProviderInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly AssetRepository $assetRepository,
        private readonly RenditionManager $renditionManager,
        private readonly FileUrlResolver $fileUrlResolver,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?AssetDuplicateOutput
    {
        $asset = $this->assetRepository->find($uriVariables['id']);
        if (!$asset instanceof Asset) {
            return null;
        }
        $this->denyAccessUnlessGranted(AbstractVoter::READ, $asset);

        $analysis = $asset->getSource()?->getAnalysis() ?? [];

        $fileIds = [];
        foreach ($analysis['results'] ?? [] as $result) {
            foreach ($result['output']['duplicates'] ?? [] as $fileId) {
                $fileIds[$fileId] = true;
            }
        }

        $duplicates = [];
        foreach ($this->assetRepository->findBySourceFileIds(array_keys($fileIds)) as $duplicate) {
            if ($duplicate->getId() === $asset->getId() || !$this->isGranted(AbstractVoter::READ, $duplicate)) {
                continue;
            }

            $duplicates[] = [
                'id' => $duplicate->getId(),
                'title' => $duplicate->getSource()?->getFileName(),
                'thumbnailUrl' => $this->resolveThumbnailUrl($duplicate),
                'createdAt' => $duplicate->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            ];
        }

        return new AssetDuplicateOutput($duplicates);
    }

    private function resolveThumbnailUrl(Asset $asset): ?string
    {
        foreach ($this->renditionManager->getAssetRenditionsUsedAs('thumbnail', $asset->getId()) as $rendition) {
            $file = $rendition->getFile();
            if (null !== $file && $this->isGranted(AbstractVoter::READ, $rendition)) {
                return $this->fileUrlResolver->resolveUrl($file);
            }
        }

        return null;
    }
}
