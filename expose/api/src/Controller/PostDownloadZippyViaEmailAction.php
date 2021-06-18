<?php

declare(strict_types=1);

namespace App\Controller;

use Alchemy\ReportBundle\ReportUserService;
use App\Consumer\Handler\ZippyDownloadRequestHandler;
use App\Entity\DownloadRequest;
use App\Entity\Publication;
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
 * @Route("/publications/{id}/zippy/download-request", name="download_zippy_request_create", methods={"POST"})
 */
final class PostDownloadZippyViaEmailAction extends AbstractController
{
    private EntityManagerInterface $em;
    private ReportUserService $reportClient;

    public function __construct(EntityManagerInterface $em, ReportUserService $reportClient)
    {
        $this->em = $em;
        $this->reportClient = $reportClient;
    }

    public function __invoke(string $id, Request $request, EventProducer $eventProducer): Response
    {
        /** @var Publication|null $publication */
        $publication = $this->em->find(Publication::class, $id);

        if (!$publication instanceof Publication) {
            throw new NotFoundHttpException();
        }

        $this->denyAccessUnlessGranted(PublicationVoter::READ_DETAILS, $publication);

        $downloadRequest = new DownloadRequest();
        $downloadRequest->setPublication($publication);
        $downloadRequest->setEmail($request->request->get('email'));
        $downloadRequest->setLocale($request->getLocale());

        $this->em->persist($downloadRequest);
        $this->em->flush();

        $eventProducer->publish(new EventMessage(ZippyDownloadRequestHandler::EVENT, [
            'id' => $downloadRequest->getId(),
        ]));

        $this->reportClient->pushHttpRequestLog(
            $request,
            ExposeLogActionInterface::PUBLICATION_ARCHIVE_DOWNLOAD_REQUEST,
            $publication->getId(),
            [
                'publicationTitle' => $publication->getTitle(),
                'recipient' => $downloadRequest->getEmail(),
            ]
        );

        return new JsonResponse(true);
    }
}
