<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Api\Model\Output\StoryThumbnailsOutput;
use App\Asset\FileUrlResolver;
use App\Elasticsearch\AssetSearch;
use App\Entity\Core\Asset;
use App\Repository\Core\AssetRepository;
use App\Security\RenditionPermissionManager;
use App\Security\Voter\AbstractVoter;
use App\Storage\RenditionManager;

class StoryThumbnailsProvider implements ProviderInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly AssetSearch $assetSearch,
        private readonly RenditionManager $renditionManager,
        private readonly RenditionPermissionManager $renditionPermissionManager,
        private readonly FileUrlResolver $fileUrlResolver,
        private readonly AssetRepository $assetRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $storyAsset = $this->assetRepository->find($uriVariables['id']);
        if (!$storyAsset instanceof Asset) {
            return null;
        }
        $this->denyAccessUnlessGranted(AbstractVoter::READ, $storyAsset);
        if (null === $storyAsset->getStoryCollection()) {
            return null;
        }

        $user = $this->security->getUser();
        $userId = $user instanceof JwtUser ? $user->getId() : null;
        $groupIds = $user instanceof JwtUser ? $user->getGroups() : [];

        $options = $context['filters'] ?? [];
        $options['parents'] = [$storyAsset->getStoryCollection()->getId()];

        [$result] = $this->assetSearch->search($userId, $groupIds, $options);
        dump($result);
        $thumbnails = [];
        foreach ($result as $asset) {
            dump($asset->getId());
            $renditions = $this->renditionManager->getAssetRenditionsUsedAs('thumbnail', $asset->getId());
            foreach ($renditions as $rendition) {
                if (str_starts_with($rendition->getFile()?->getType() ?? '', 'image/')) {
                    if ($this->renditionPermissionManager->isGranted($asset, $rendition->getDefinition()->getPolicy(), $userId, $groupIds)) {
                        $thumbnails[] = $this->fileUrlResolver->resolveUrl($rendition->getFile());
                        break;
                    }
                }
            }
        }

        return new StoryThumbnailsOutput($thumbnails);
    }
}
