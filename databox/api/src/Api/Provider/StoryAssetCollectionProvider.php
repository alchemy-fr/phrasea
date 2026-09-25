<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use App\Repository\Core\AssetRepository;
use App\Service\Collection\CollectionDestinationResolver;

final class StoryAssetCollectionProvider extends AbstractCollectionAssetCollectionProvider
{
    public function __construct(
        private readonly AssetRepository $assetRepository,
        private readonly CollectionDestinationResolver $destinationResolver,
    ) {
    }

    protected function resolveTarget(array $uriVariables): array
    {
        $storyAsset = DoctrineUtil::findStrictByRepo($this->assetRepository, $uriVariables['id'], throw404: true);

        // A story collection has no indexed path of its own: its assets are found by story ID
        return [
            $this->destinationResolver->resolveStoryCollection($storyAsset),
            ['story' => $storyAsset->getId()],
        ];
    }
}
