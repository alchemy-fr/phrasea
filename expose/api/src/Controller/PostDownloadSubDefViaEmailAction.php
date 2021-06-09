<?php

declare(strict_types=1);

namespace App\Controller;

use Alchemy\ReportBundle\ReportUserService;
use App\Consumer\Handler\DownloadRequestHandler;
use App\Entity\DownloadRequest;
use App\Entity\Publication;
use App\Entity\PublicationAsset;
use App\Entity\SubDefinition;
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
 * @Route("/publications/{publicationId}/subdef/{subDefId}/download-request", name="download_subdef_request_create", methods={"POST"})
 */
final class PostDownloadSubDefViaEmailAction extends AbstractController
{
    private EntityManagerInterface $em;
    private ReportUserService $reportClient;

    public function __construct(EntityManagerInterface $em, ReportUserService $reportClient)
    {
        $this->em = $em;
        $this->reportClient = $reportClient;
    }

    public function __invoke(string $publicationId, string $subDefId, Request $request, EventProducer $eventProducer): Response
    {
        /** @var Publication|null $publication */
        $publication = $this->em->getRepository(Publication::class)->find($publicationId);

        if (!$publication instanceof Publication) {
            throw new NotFoundHttpException();
        }

        $this->denyAccessUnlessGranted(PublicationVoter::READ_DETAILS, $publication);

        $subDef = $this->em->getRepository(SubDefinition::class)->find($subDefId);
        if (!$subDef instanceof SubDefinition) {
            throw new NotFoundHttpException(sprintf('Sub def "%s" not found', $subDefId));
        }

        $publicationAsset = $this->em->getRepository(PublicationAsset::class)
            ->findOneBy([
                'publication' => $publication->getId(),
                'asset' => $subDef->getAsset()->getId(),
            ]);

        if (!$publicationAsset instanceof PublicationAsset) {
            throw new NotFoundHttpException('PublicationAsset not found');
        }

        $asset = $publicationAsset->getAsset();

        $downloadRequest = new DownloadRequest();
        $downloadRequest->setPublication($publicationAsset->getPublication());
        $downloadRequest->setAsset($publicationAsset->getAsset());
        $downloadRequest->setSubDefinition($subDef);
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
                'subDefinitionName' => $subDef->getName(),
            ]
        );

        return new JsonResponse(true);
    }
}
