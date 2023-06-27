<?php

declare(strict_types=1);

namespace App\Controller;

use Alchemy\ReportBundle\ReportUserService;
use App\Entity\Publication;
use App\Report\ExposeLogActionInterface;
use App\Security\Voter\PublicationVoter;
use App\ZippyManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/publications/{id}/download-via-zippy', name: 'archive_download', methods: ['GET'])]
final class DownloadViaZippyAction extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly ZippyManager $zippyManager, private readonly ReportUserService $reportClient)
    {
    }

    public function __invoke(string $id, Request $request): Response
    {
        /** @var Publication $publication */
        $publication = $this->em->find(Publication::class, $id);
        if (!$publication) {
            throw new NotFoundHttpException();
        }
        $this->denyAccessUnlessGranted(PublicationVoter::READ_DETAILS, $publication);

        $this->reportClient->pushHttpRequestLog(
            $request,
            ExposeLogActionInterface::PUBLICATION_ARCHIVE_DOWNLOAD,
            $publication->getId(),
            [
                'publicationTitle' => $publication->getTitle(),
            ]
        );

        return new RedirectResponse($this->zippyManager->getDownloadUrl($publication));
    }
}
