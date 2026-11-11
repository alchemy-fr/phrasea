<?php

declare(strict_types=1);

namespace App\Controller;

use Alchemy\ReportBundle\ReportUserService;
use App\Archive\PublicationArchiver;
use App\Entity\Publication;
use App\Report\ExposeLogActionInterface;
use App\Security\AssetUrlGenerator;
use App\Security\PublicationDownloadPolicy;
use App\Security\Voter\PublicationVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Redirects to the archive of the publication when it is up to date. Otherwise asks the
 * workers to build it and answers 202 `{"status": "preparing"}`: the client asks again later.
 *
 * A client asking for JSON (`Accept: application/json`) gets the URL of the archive in a
 * 200 `{"status": "ready", "url": …}` instead of the redirection, which it cannot follow
 * without downloading the archive.
 */
#[Route(path: '/publications/{id}/archive', name: 'archive_download', methods: ['GET'])]
final class DownloadPublicationArchiveAction extends AbstractController
{
    public const string STATUS_PREPARING = 'preparing';
    public const string STATUS_READY = 'ready';

    /**
     * Seconds suggested to the client before asking again.
     */
    private const int RETRY_AFTER = 5;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PublicationArchiver $archiver,
        private readonly AssetUrlGenerator $assetUrlGenerator,
        private readonly ReportUserService $reportClient,
        private readonly PublicationDownloadPolicy $downloadPolicy,
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
        $this->downloadPolicy->denyDirectDownloadUnlessGranted($publication, $request);

        $archivePath = $this->archiver->getUpToDateArchivePath($publication);
        if (null === $archivePath) {
            $this->archiver->requestBuild($publication);

            return new JsonResponse(['status' => self::STATUS_PREPARING], Response::HTTP_ACCEPTED, [
                'Retry-After' => (string) self::RETRY_AFTER,
            ]);
        }

        $this->reportClient->pushHttpRequestLog(
            $request,
            ExposeLogActionInterface::PUBLICATION_ARCHIVE_DOWNLOAD,
            $publication->getId(),
            [
                'publicationTitle' => $publication->getTitle(),
            ]
        );

        $url = $this->assetUrlGenerator->generatePathUrl($archivePath, true);
        if (in_array($request->getPreferredFormat(), ['json', 'jsonld'], true)) {
            return new JsonResponse(['status' => self::STATUS_READY, 'url' => $url]);
        }

        return new RedirectResponse($url);
    }
}
