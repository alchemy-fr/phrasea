<?php

declare(strict_types=1);

namespace App\Controller;

use App\Storage\SubDefinitionManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

final class DeletePublicationAssetsAction extends AbstractController
{
    private SubDefinitionManager $assetManager;

    public function __construct(SubDefinitionManager $assetManager)
    {
        $this->assetManager = $assetManager;
    }

    public function __invoke(string $publicationId, string $assetId, Request $request): void
    {
        $this->assetManager->deletePublicationAssetsByPublicationAndAsset($publicationId, $assetId);
    }
}
