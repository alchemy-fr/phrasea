<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use Alchemy\NotifyBundle\Notify\NotifierInterface;
use App\Entity\DownloadRequest;
use App\Security\Authentication\JWTManager;
use App\ZippyManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsMessageHandler]
final readonly class ZippyDownloadRequestHandler
{
    public function __construct(
        private NotifierInterface $notifier,
        private ZippyManager $zippyManager,
        private JWTManager $JWTManager,
        private UrlGeneratorInterface $urlGenerator,
        private EntityManagerInterface $em,
    ) {
    }

    public function __invoke(ZippyDownloadRequest $message): void
    {
        $downloadRequest = DoctrineUtil::findStrict($this->em, DownloadRequest::class, $message->getId());

        // Trigger ZIP preparation
        $this->zippyManager->getDownloadUrl($downloadRequest->getPublication());

        $uri = $this->urlGenerator->generate('archive_download', [
            'id' => $downloadRequest->getPublication()->getId(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);
        $downloadUrl = $this->JWTManager->signUri(
            $uri,
            259200 // 3 days
        );

        $this->notifier->sendEmail(
            $downloadRequest->getEmail(),
            'expose/zippy_download_link',
            $downloadRequest->getLocale(),
            [
                'download_url' => $downloadUrl,
            ]
        );
    }
}
