<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use App\Api\Model\Input\MultipleAssetInput;
use App\Entity\Core\Asset;

class MultipleAssetInputProcessor extends AbstractFileInputProcessor
{
    public function __construct(private readonly AssetInputProcessor $assetInputProcessor)
    {
    }

    /**
     * @param MultipleAssetInput $data
     */
    protected function transform(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $assets = [];
        $context[AssetInputProcessor::CONTEXT_CREATION_MICRO_TIME] = microtime(true);
        foreach ($data->assets as $asset) {
            $assets[] = $this->assetInputProcessor->process($asset, $operation, $uriVariables, $context);
        }

        return $assets;
    }
}
