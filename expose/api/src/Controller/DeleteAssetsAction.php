<?php

declare(strict_types=1);

namespace App\Controller;

use App\Storage\AssetManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class DeleteAssetsAction extends AbstractController
{
    public function __construct(private readonly AssetManager $assetManager)
    {
    }

    public function __invoke(string $assetId): void
    {
        $this->assetManager->deleteByAssetId($assetId);
    }
}
