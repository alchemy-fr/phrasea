<?php

declare(strict_types=1);

namespace App\Controller;

use App\Report\ExposeLogActionInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/publications/{publicationId}/subdef/{subDefId}/download', name: 'download_subdef', methods: ['GET'])]
final class DownloadSubDefAction extends AbstractAssetAction
{
    public function __invoke(string $publicationId, string $subDefId, Request $request): RedirectResponse
    {
        $publication = $this->getPublication($publicationId);
        $subDef = $this->getSubDefOfPublication($subDefId, $publication);
        $asset = $subDef->getAsset();

        $this->reportClient->pushHttpRequestLog(
            $request,
            ExposeLogActionInterface::ASSET_DOWNLOAD,
            $asset->getId(),
            [
                'publicationId' => $publication->getId(),
                'publicationTitle' => $publication->getTitle(),
                'assetTitle' => $asset->getTitle(),
                'subDefinitionName' => $subDef->getName(),
                'subDefinitionId' => $subDef->getId(),
            ]
        );

        return new RedirectResponse($this->assetUrlGenerator->generateSubDefinitionUrl($subDef, true));
    }
}
