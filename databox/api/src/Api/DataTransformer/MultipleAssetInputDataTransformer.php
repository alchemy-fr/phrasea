<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use App\Api\Model\Input\MultipleAssetInput;
use App\Entity\Core\Asset;

class MultipleAssetInputDataTransformer extends AbstractFileInputDataTransformer
{
    public function __construct(private readonly AssetInputDataTransformer $assetInputDataTransformer)
    {
    }

    /**
     * @param MultipleAssetInput $data
     */
    public function transform($data, string $to, array $context = [])
    {
        $assets = [];
        $context[AssetInputDataTransformer::CONTEXT_CREATION_MICRO_TIME] = microtime(true);
        foreach ($data->assets as $asset) {
            $assets[] = $this->assetInputDataTransformer->transform($asset, $to, $context);
        }

        return $assets;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        if (!is_array($data)) {
            return false;
        }

        return Asset::class === $to && MultipleAssetInput::class === ($context['input']['class'] ?? null);
    }
}
