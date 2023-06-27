<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use Alchemy\NotifyBundle\Notify\NotifierInterface;
use App\Entity\DownloadRequest;
use App\Security\Authentication\JWTManager;
use App\ZippyManager;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Consumer\Exception\ObjectNotFoundForHandlerException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ZippyDownloadRequestHandler extends AbstractEntityManagerHandler
{
    final public const EVENT = 'zippy_download_request';

    public function __construct(private readonly NotifierInterface $notifier, private readonly ZippyManager $zippyManager, private readonly JWTManager $JWTManager, private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    public function handle(EventMessage $message): void
    {
        $id = $message->getPayload()['id'];

        $em = $this->getEntityManager();
        $downloadRequest = $em->find(DownloadRequest::class, $id);
        if (!$downloadRequest instanceof DownloadRequest) {
            throw new ObjectNotFoundForHandlerException(DownloadRequest::class, $id, self::class);
        }

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

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
