<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Storage\AssetManager;
use Symfony\Component\HttpFoundation\Request;

final readonly class DeleteAssetsByAssetIdProcessor implements ProcessorInterface
{
    public function __construct(private AssetManager $assetManager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        /** @var Request $request */
        $request = $context['request'];

        $this->assetManager->deleteByAssetId((string) $request->attributes->get('assetId'));

        return null;
    }
}
