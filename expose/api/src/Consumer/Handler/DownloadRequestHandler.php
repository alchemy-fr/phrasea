<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use Alchemy\AuthBundle\Security\UriJwtManager;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use Alchemy\NotifyBundle\Notification\NotifierInterface;
use App\Entity\DownloadRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsMessageHandler]
final readonly class DownloadRequestHandler
{
    public function __construct(
        private NotifierInterface $notifier,
        private UrlGeneratorInterface $urlGenerator,
        private UriJwtManager $uriJwtManager,
        private EntityManagerInterface $em,
    ) {
    }

    public function __invoke(DownloadRequest $message): void
    {
        $downloadRequest = DoctrineUtil::findStrict($this->em, DownloadRequest::class, $message->getId());

        $parameters = [
            'publicationId' => $downloadRequest->getPublication()->getId(),
        ];
        if ($downloadRequest->getSubDefinition()) {
            $parameters['subDefId'] = $downloadRequest->getSubDefinition()->getId();
        } else {
            $parameters['assetId'] = $downloadRequest->getAsset()->getId();
        }
        $uri = $this->urlGenerator->generate($downloadRequest->getSubDefinition() ? 'download_subdef' : 'download_asset', $parameters, UrlGeneratorInterface::ABSOLUTE_URL);

        $downloadUrl = $this->uriJwtManager->signUri(
            $uri,
            259200 // 3 days
        );

        $this->notifier->sendEmail(
            $downloadRequest->getEmail(),
            'expose-download-link',
            [
                'locale' => $downloadRequest->getLocale(),
                'downloadUrl' => $downloadUrl,
            ]
        );
    }
}
