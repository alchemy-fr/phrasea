<?php

declare(strict_types=1);

namespace App\Controller;

use Alchemy\ReportBundle\ReportUserService;
use App\Consumer\Handler\DownloadRequestHandler;
use App\Entity\DownloadRequest;
use App\Entity\PublicationAsset;
use App\Report\ExposeLogActionInterface;
use App\Security\Voter\PublicationVoter;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/publications/{publicationId}/assets/{assetId}/download-request", name="download_asset_request_create", methods={"POST"})
 */
final class PostDownloadAssetViaEmailAction extends AbstractController
{
    private EntityManagerInterface $em;
    private ReportUserService $reportClient;

    public function __construct(EntityManagerInterface $em, ReportUserService $reportClient)
    {
        $this->em = $em;
        $this->reportClient = $reportClient;
    }

    public function __invoke(string $publicationId, string $assetId, Request $request, EventProducer $eventProducer): Response
    {
        /** @var PublicationAsset|null $publicationAsset */
        $publicationAsset = $this->em
            ->getRepository(PublicationAsset::class)
            ->findOneBy([
                'publication' => $publicationId,
                'asset' => $assetId,
            ]);

        if (!$publicationAsset instanceof PublicationAsset) {
            throw new NotFoundHttpException();
        }

        $this->denyAccessUnlessGranted(PublicationVoter::READ_DETAILS, $publicationAsset->getPublication());

        $downloadRequest = new DownloadRequest();
        $downloadRequest->setPublication($publicationAsset->getPublication());
        $downloadRequest->setAsset($publicationAsset->getAsset());
        $downloadRequest->setEmail($request->request->get('email'));
        $downloadRequest->setLocale($request->getLocale());

        $this->em->persist($downloadRequest);
        $this->em->flush();

        $eventProducer->publish(new EventMessage(DownloadRequestHandler::EVENT, [
            'id' => $downloadRequest->getId(),
        ]));

        $this->reportClient->pushHttpRequestLog(
            $request,
            ExposeLogActionInterface::ASSET_DOWNLOAD_REQUEST,
            $publicationAsset->getAsset()->getId(),
            [
                'publicationId' => $publicationAsset->getPublication()->getId(),
                'recipient' => $downloadRequest->getEmail(),
            ]
        );

        return new JsonResponse(true);
    }
}
