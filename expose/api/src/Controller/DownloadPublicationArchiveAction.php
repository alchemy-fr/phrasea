<?php

declare(strict_types=1);

namespace App\Controller;

use Alchemy\ReportBundle\ReportUserService;
use App\Archive\PublicationArchiver;
use App\Entity\Publication;
use App\Report\ExposeLogActionInterface;
use App\Security\AssetUrlGenerator;
use App\Security\Voter\PublicationVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/publications/{id}/archive', name: 'archive_download', methods: ['GET'])]
final class DownloadPublicationArchiveAction extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PublicationArchiver $archiver,
        private readonly AssetUrlGenerator $assetUrlGenerator,
        private readonly ReportUserService $reportClient,
    ) {
    }

    public function __invoke(string $id, Request $request): Response
    {
        /** @var Publication|null $publication */
        $publication = $this->em->find(Publication::class, $id);
        if (!$publication instanceof Publication) {
            throw new NotFoundHttpException();
        }
        $this->denyAccessUnlessGranted(PublicationVoter::READ_DETAILS, $publication);

        if ($publication->getAssets()->isEmpty()) {
            throw new NotFoundHttpException('Publication has no asset to archive.');
        }

        $this->reportClient->pushHttpRequestLog(
            $request,
            ExposeLogActionInterface::PUBLICATION_ARCHIVE_DOWNLOAD,
            $publication->getId(),
            [
                'publicationTitle' => $publication->getTitle(),
            ]
        );

        $archivePath = $this->archiver->getArchivePath($publication);

        return new RedirectResponse($this->assetUrlGenerator->generatePathUrl($archivePath, true));
    }
}
