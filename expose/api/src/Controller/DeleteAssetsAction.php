<?php

declare(strict_types=1);

namespace App\Controller;

use App\Storage\AssetManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

final class DeleteAssetsAction extends AbstractController
{
    public function __construct(private readonly AssetManager $assetManager)
    {
    }

    public function __invoke(string $assetId, Request $request): void
    {
        $this->assetManager->deleteByAssetId($assetId);
    }
}
