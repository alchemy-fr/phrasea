<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use Alchemy\NotifyBundle\Notify\NotifierInterface;
use App\Entity\DownloadRequest;
use App\ZippyManager;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Consumer\Exception\ObjectNotFoundForHandlerException;

class ZippyDownloadRequestHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'zippy_download_request';

    private NotifierInterface $notifier;
    private ZippyManager $zippyManager;

    public function __construct(NotifierInterface $notifier, ZippyManager $zippyManager)
    {
        $this->notifier = $notifier;
        $this->zippyManager = $zippyManager;
    }

    public function handle(EventMessage $message): void
    {
        $id = $message->getPayload()['id'];

        $em = $this->getEntityManager();
        $downloadRequest = $em->find(DownloadRequest::class, $id);
        if (!$downloadRequest instanceof DownloadRequest) {
            throw new ObjectNotFoundForHandlerException(DownloadRequest::class, $id, __CLASS__);
        }

        $downloadUrl = $this->zippyManager->getDownloadUrl($downloadRequest->getPublication());

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
