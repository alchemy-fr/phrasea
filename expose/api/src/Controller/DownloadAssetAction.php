<?php

declare(strict_types=1);

namespace App\Controller;

use App\Report\ExposeLogActionInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/publications/{publicationId}/assets/{assetId}/download', name: 'download_asset')]
final class DownloadAssetAction extends AbstractAssetAction
{
    public function __invoke(string $publicationId, string $assetId, Request $request): RedirectResponse
    {
        $publication = $this->getPublication($publicationId);
        $asset = $this->getAssetOfPublication($assetId, $publication);

        $this->reportClient->pushHttpRequestLog(
            $request,
            ExposeLogActionInterface::ASSET_DOWNLOAD,
            $asset->getId(),
            [
                'publicationId' => $publication->getId(),
                'publicationTitle' => $publication->getTitle(),
                'assetTitle' => $asset->getTitle(),
            ]
        );

        return new RedirectResponse($this->assetUrlGenerator->generateAssetUrl($asset, true));
    }
}
