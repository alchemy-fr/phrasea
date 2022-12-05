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

/**
 * @Route("/publications/{id}/download-via-zippy", name="archive_download", methods={"GET"})
 */
final class DownloadViaZippyAction extends AbstractController
{
    private EntityManagerInterface $em;
    private ZippyManager $zippyManager;
    private ReportUserService $reportClient;

    public function __construct(EntityManagerInterface $em, ZippyManager $zippyManager, ReportUserService $reportClient)
    {
        $this->em = $em;
        $this->zippyManager = $zippyManager;
        $this->reportClient = $reportClient;
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
