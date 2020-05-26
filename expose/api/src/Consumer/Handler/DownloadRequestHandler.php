<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use Alchemy\NotifyBundle\Notify\NotifierInterface;
use App\Entity\DownloadRequest;
use App\Security\AssetUrlGenerator;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Consumer\Exception\ObjectNotFoundForHandlerException;

class DownloadRequestHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'download_request';

    private NotifierInterface $notifier;
    private AssetUrlGenerator $assetUrlGenerator;

    public function __construct(NotifierInterface $notifier, AssetUrlGenerator $assetUrlGenerator)
    {
        $this->notifier = $notifier;
        $this->assetUrlGenerator = $assetUrlGenerator;
    }

    public function handle(EventMessage $message): void
    {
        $id = $message->getPayload()['id'];

        $em = $this->getEntityManager();
        $downloadRequest = $em->find(DownloadRequest::class, $id);
        if (!$downloadRequest instanceof DownloadRequest) {
            throw new ObjectNotFoundForHandlerException(DownloadRequest::class, $id, __CLASS__);
        }

        $this->notifier->sendEmail(
            $downloadRequest->getEmail(),
            'expose/download_link',
            $downloadRequest->getLocale(),
            [
                'download_url' => $this->assetUrlGenerator->generateAssetUrl($downloadRequest->getAsset(), true),
            ]
        );
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
