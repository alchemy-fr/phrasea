<?php

declare(strict_types=1);

namespace App\Service\Asset;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use App\Entity\Core\Asset;
use App\Entity\Core\File;
use App\Repository\Core\AssetRepository;
use App\Repository\Core\FileDuplicateRepository;
use App\Security\Voter\AbstractVoter;
use App\Service\Asset\Attribute\AssetNameResolver;
use App\Service\Storage\RenditionManager;

/**
 * Resolves the duplicate links of an analyzed file into the assets owning the
 * duplicate source files, so clients can display them with their thumbnail.
 */
class DuplicateAssetResolver
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly FileDuplicateRepository $fileDuplicateRepository,
        private readonly AssetRepository $assetRepository,
        private readonly RenditionManager $renditionManager,
        private readonly FileUrlResolver $fileUrlResolver,
        private readonly AssetNameResolver $assetNameResolver,
    ) {
    }

    /**
     * @return array<int, array{id: string, title: ?string, thumbnail: ?array{id: string, url: string, type: ?string}, sourceType: ?string, createdAt: ?string, analyzers: string[]}>
     */
    public function resolveDuplicates(File $file, ?string $excludeAssetId = null): array
    {
        $fileIdAnalyzers = [];
        foreach ($this->fileDuplicateRepository->findByFileId($file->getId()) as $link) {
            $fileIdAnalyzers[$link->getDuplicateFile()->getId()][] = $link->getAnalyzer();
        }

        $duplicates = [];
        foreach ($this->assetRepository->findBySourceFileIds(array_keys($fileIdAnalyzers)) as $duplicate) {
            if ($duplicate->getId() === $excludeAssetId
                || $duplicate->isDeleted()
                || !$this->isGranted(AbstractVoter::READ, $duplicate)
            ) {
                continue;
            }

            $sourceId = $duplicate->getSource()?->getId();

            $duplicates[] = [
                'id' => $duplicate->getId(),
                'title' => $this->assetNameResolver->resolveNameAsString($duplicate),
                'thumbnail' => $this->resolveThumbnail($duplicate),
                'sourceType' => $duplicate->getSource()?->getType(),
                'createdAt' => $duplicate->getCreatedAt()?->format(\DateTimeInterface::ATOM),
                'analyzers' => $fileIdAnalyzers[$sourceId] ?? [],
            ];
        }

        return $duplicates;
    }

    /**
     * @return ?array{id: string, url: string, type: ?string}
     */
    private function resolveThumbnail(Asset $asset): ?array
    {
        foreach ($this->renditionManager->getAssetRenditionsUsedAs('thumbnail', $asset->getId()) as $rendition) {
            $file = $rendition->getFile();
            if (null !== $file && $this->isGranted(AbstractVoter::READ, $rendition)) {
                return [
                    'id' => $file->getId(),
                    'url' => $this->fileUrlResolver->resolveUrl($file),
                    'type' => $file->getType(),
                ];
            }
        }

        return null;
    }
}
