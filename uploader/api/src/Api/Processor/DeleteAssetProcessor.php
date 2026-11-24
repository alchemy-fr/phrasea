<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Asset;
use App\Storage\AssetManager;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The voter only lets a file not committed yet be deleted.
 */
final readonly class DeleteAssetProcessor implements ProcessorInterface
{
    public function __construct(private AssetManager $assetManager)
    {
    }

    /**
     * @param Asset $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        if (!$this->assetManager->removeUncommitted($data)) {
            throw new NotFoundHttpException('Asset not found');
        }

        return null;
    }
}
