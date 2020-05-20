<?php

declare(strict_types=1);

namespace App\Controller;

use App\Consumer\Handler\DownloadRequestHandler;
use App\Entity\DownloadRequest;
use App\Entity\PublicationAsset;
use App\Security\Voter\PublicationVoter;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/publications/{id}/assets/{assetId}/download-request", name="download_request_create")
 */
final class PostDownloadViaEmailAction extends AbstractController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function __invoke(string $id, string $assetId, Request $request, EventProducer $eventProducer): Response
    {
        /** @var PublicationAsset|null $publicationAsset */
        $publicationAsset = $this->em
            ->getRepository(PublicationAsset::class)
            ->findOneBy([
                'publication' => $id,
                'asset'=> $assetId,
            ]);

        if (!$publicationAsset instanceof PublicationAsset) {
            throw new NotFoundHttpException();
        }

        if (!$this->isGranted(PublicationVoter::READ, $publicationAsset->getPublication())) {
            throw new AccessDeniedHttpException();
        }

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

        return new JsonResponse(true);
    }
}
