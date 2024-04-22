<?php

declare(strict_types=1);

namespace App\Controller;

use App\Consumer\Handler\DownloadRequest as DownloadRequestMessage;
use App\Entity\DownloadRequest;
use App\Report\ExposeLogActionInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/publications/{publicationId}/assets/{assetId}/download-request', name: 'download_asset_request_create', methods: ['POST'])]
final class PostDownloadAssetViaEmailAction extends AbstractAssetAction
{
    public function __invoke(
        string $publicationId,
        string $assetId,
        Request $request,
        MessageBusInterface $bus
    ): Response {
        $publication = $this->getPublication($publicationId);
        $asset = $this->getAssetOfPublication($assetId, $publication);

        $downloadRequest = new DownloadRequest();
        $downloadRequest->setPublication($publication);
        $downloadRequest->setAsset($asset);
        $downloadRequest->setEmail($request->request->get('email'));
        $downloadRequest->setLocale($request->getLocale());

        $this->em->persist($downloadRequest);
        $this->em->flush();

        $bus->dispatch(new DownloadRequestMessage($downloadRequest->getId()));

        $this->reportClient->pushHttpRequestLog(
            $request,
            ExposeLogActionInterface::ASSET_DOWNLOAD_REQUEST,
            $asset->getId(),
            [
                'publicationId' => $publication->getId(),
                'publicationTitle' => $publication->getTitle(),
                'assetTitle' => $asset->getTitle(),
                'recipient' => $downloadRequest->getEmail(),
            ]
        );

        return new JsonResponse(true);
    }
}
