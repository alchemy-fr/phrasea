<?php

declare(strict_types=1);

namespace App\Controller;

use App\Report\ExposeLogActionInterface;
use App\Security\DownloadMode;
use App\Security\PublicationDownloadPolicy;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Exchanges the acceptance of the download terms for a short-lived direct download URL of an
 * asset (`assetId`), a rendition (`subDefId`) or the archive (`archive: true`).
 */
#[Route(path: '/publications/{id}/download-url', name: 'publication_download_url', methods: ['POST'])]
final class PostPublicationDownloadUrlAction extends AbstractAssetAction
{
    public function __invoke(string $id, Request $request): JsonResponse
    {
        $publication = $this->getPublication($id);
        $this->downloadPolicy->denyDownloadUrlUnlessGranted($publication, $request);

        $body = $request->request->all();
        $assetId = $body['assetId'] ?? null;
        $subDefId = $body['subDefId'] ?? null;
        $archive = true === ($body['archive'] ?? null);
        if (1 !== count(array_filter([null !== $assetId, null !== $subDefId, $archive]))
            || (null !== $assetId && !is_string($assetId))
            || (null !== $subDefId && !is_string($subDefId))) {
            throw new BadRequestHttpException('Expected one of "assetId", "subDefId" or "archive": true.');
        }

        $logPayload = [
            'publicationId' => $publication->getId(),
            'publicationTitle' => $publication->getTitle(),
        ];
        if (null !== $assetId) {
            $asset = $this->getAssetOfPublication($assetId, $publication);
            $logItemId = $asset->getId();
            $logPayload['assetTitle'] = $asset->getTitle();
            $uri = $this->generateUrl('download_asset', [
                'publicationId' => $publication->getId(),
                'assetId' => $asset->getId(),
            ], UrlGeneratorInterface::ABSOLUTE_URL);
        } elseif (null !== $subDefId) {
            $subDef = $this->getSubDefOfPublication($subDefId, $publication);
            $asset = $subDef->getAsset();
            $logItemId = $asset->getId();
            $logPayload['assetTitle'] = $asset->getTitle();
            $logPayload['subDefinitionName'] = $subDef->getName();
            $logPayload['subDefinitionId'] = $subDef->getId();
            $uri = $this->generateUrl('download_subdef', [
                'publicationId' => $publication->getId(),
                'subDefId' => $subDef->getId(),
            ], UrlGeneratorInterface::ABSOLUTE_URL);
        } else {
            if ($publication->getAssets()->isEmpty()) {
                throw new NotFoundHttpException('Publication has no asset to archive.');
            }
            $logItemId = $publication->getId();
            $logPayload['archive'] = true;
            $uri = $this->generateUrl('archive_download', [
                'id' => $publication->getId(),
            ], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        if (DownloadMode::Terms === $this->downloadPolicy->getMode($publication)) {
            $this->reportClient->pushHttpRequestLog(
                $request,
                ExposeLogActionInterface::DOWNLOAD_TERMS_ACCEPTED,
                $logItemId,
                $logPayload,
            );
        }

        return new JsonResponse([
            'url' => $this->downloadPolicy->signDirectDownloadUri($uri, PublicationDownloadPolicy::ACCEPTED_TERMS_URL_TTL),
        ]);
    }
}
