<?php

declare(strict_types=1);

namespace App\Controller;

use App\Consumer\Handler\DownloadRequest as DownloadRequestMessage;
use App\Entity\DownloadRequest;
use App\Entity\SubDefinition;
use App\Report\ExposeLogActionInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/publications/{publicationId}/subdef/{subDefId}/download-request', name: 'download_subdef_request_create', methods: ['POST'])]
final class PostDownloadSubDefViaEmailAction extends AbstractAssetAction
{
    public function __invoke(
        string $publicationId,
        string $subDefId,
        Request $request,
        MessageBusInterface $bus,
    ): Response {
        $publication = $this->getPublication($publicationId);
        $subDef = $this->getSubDefOfPublication($subDefId, $publication);
        $asset = $subDef->getAsset();

        $subDef = $this->em->getRepository(SubDefinition::class)->find($subDefId);
        if (!$subDef instanceof SubDefinition) {
            throw new NotFoundHttpException(sprintf('Sub def "%s" not found', $subDefId));
        }

        $downloadRequest = new DownloadRequest();
        $downloadRequest->setPublication($publication);
        $downloadRequest->setAsset($asset);
        $downloadRequest->setSubDefinition($subDef);
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
                'subDefinitionName' => $subDef->getName(),
            ]
        );

        return new JsonResponse(true);
    }
}
