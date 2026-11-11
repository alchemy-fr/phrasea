<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use Alchemy\NotifierBundle\Manager\NotifierManager;
use App\Archive\PublicationArchiver;
use App\Entity\DownloadRequest;
use App\Entity\Publication;
use App\Security\PublicationDownloadPolicy;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsMessageHandler]
final readonly class PublicationArchiveRequestHandler
{
    public function __construct(
        private NotifierManager $notifier,
        private PublicationArchiver $archiver,
        private PublicationDownloadPolicy $downloadPolicy,
        private UrlGeneratorInterface $urlGenerator,
        private EntityManagerInterface $em,
    ) {
    }

    public function __invoke(PublicationArchiveRequest $message): void
    {
        $publication = $this->em->find(Publication::class, $message->getPublicationId());
        if (!$publication instanceof Publication) {
            throw new UnrecoverableMessageHandlingException(sprintf('Publication "%s" not found.', $message->getPublicationId()));
        }

        if ($publication->getAssets()->isEmpty()) {
            throw new UnrecoverableMessageHandlingException(sprintf('Publication "%s" has no asset to archive.', $publication->getId()));
        }

        // The archive is built before the email is sent, so that the link works right away.
        $this->archiver->buildArchive($publication);

        if (null === $downloadRequestId = $message->getDownloadRequestId()) {
            return;
        }

        $downloadRequest = DoctrineUtil::findStrict($this->em, DownloadRequest::class, $downloadRequestId);

        $daysAvailable = 3;
        $uri = $this->urlGenerator->generate('archive_download', [
            'id' => $publication->getId(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);
        $downloadUrl = $this->downloadPolicy->signEmailDownloadUri(
            $uri,
            $daysAvailable * 3600 * 24,
        );

        $this->notifier->notifyEmail(
            $downloadRequest->getEmail(),
            'download:archive_link',
            [
                'downloadUrl' => $downloadUrl,
                'daysAvailable' => $daysAvailable,
            ],
            $downloadRequest->getLocale(),
        );
    }
}
