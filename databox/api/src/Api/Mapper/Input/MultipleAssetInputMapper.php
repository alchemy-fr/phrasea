<?php

declare(strict_types=1);

namespace App\Api\Mapper\Input;

use App\Api\Model\Input\AssetInput;
use App\Api\Model\Input\MultipleAssetInput;

class MultipleAssetInputMapper extends AbstractFileInputMapper
{
    public function __construct(private readonly AssetInputMapper $assetInputMapper)
    {
    }

    /**
     * @param MultipleAssetInput $data
     */
    public function map(object $data, array $context = []): array
    {
        $assets = [];
        $context[AssetInputMapper::CONTEXT_CREATION_MICRO_TIME] = microtime(true);

        if ($data->isStory && !empty($data->assets)) {
            $ref = $data->assets[0];
            $storyAssetInput = new AssetInput();
            $storyAssetInput->name = $data->story?->name ?? $data->assets[0]->name ?? 'Story';
            $storyAssetInput->isStory = true;
            $storyAssetInput->tags = $data->story?->tags;
            $storyAssetInput->attributes = $data->story?->attributes;
            $storyAssetInput->workspace = $ref->workspace;
            $storyAssetInput->collection = $ref->collection;
            $storyAssetInput->destinations = $ref->destinations;

            $storyAsset = $this->assetInputMapper->map($storyAssetInput, null, $context);
            $assets[] = $storyAsset;
        }

        foreach ($data->assets as $asset) {
            if (isset($storyAsset)) {
                $asset->destinations = null;
                $asset->collection = $storyAsset->getStoryCollection();
            }

            $assets[] = $this->assetInputMapper->map($asset, null, $context);
        }

        return $assets;
    }
}
