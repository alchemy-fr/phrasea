<?php

declare(strict_types=1);

namespace App\Api\InputTransformer;

use App\Api\Model\Input\AssetInput;
use App\Api\Model\Input\MultipleAssetInput;
use App\Entity\Core\Asset;

class MultipleAssetInputTransformer extends AbstractFileInputTransformer
{
    public function __construct(private readonly AssetInputTransformer $assetInputTransformer)
    {
    }

    public function supports(string $resourceClass, object $data): bool
    {
        return Asset::class === $resourceClass && $data instanceof MultipleAssetInput;
    }

    /**
     * @param MultipleAssetInput $data
     */
    public function transform(object $data, string $resourceClass, array $context = []): object|iterable
    {
        $assets = [];
        $context[AssetInputTransformer::CONTEXT_CREATION_MICRO_TIME] = microtime(true);

        if ($data->isStory && !empty($data->assets)) {
            $ref = $data->assets[0];
            $storyAssetInput = new AssetInput();
            $storyAssetInput->title = $data->storyTitle ?? $data->assets[0]->title ?? 'Story';
            $storyAssetInput->isStory = true;
            $storyAssetInput->workspace = $ref->workspace;
            $storyAssetInput->collection = $ref->collection;
            $storyAssetInput->destinations = $ref->destinations;

            $storyAsset = $this->assetInputTransformer->transform($storyAssetInput, $storyAssetInput::class, $context);
            $assets[] = $storyAsset;
        }

        foreach ($data->assets as $asset) {
            if (isset($storyAsset)) {
                $asset->destinations = null;
                $asset->collection = $storyAsset->getStoryCollection();
            }

            $assets[] = $this->assetInputTransformer->transform($asset, $asset::class, $context);
        }

        return $assets;
    }
}
