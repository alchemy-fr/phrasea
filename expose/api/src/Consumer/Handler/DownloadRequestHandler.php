<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use Alchemy\NotifyBundle\Notify\NotifierInterface;
use App\Entity\DownloadRequest;
use App\Security\Authentication\JWTManager;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Consumer\Exception\ObjectNotFoundForHandlerException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class DownloadRequestHandler extends AbstractEntityManagerHandler
{
    final public const EVENT = 'download_request';

    public function __construct(private readonly NotifierInterface $notifier, private readonly UrlGeneratorInterface $urlGenerator, private readonly JWTManager $JWTManager)
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

        $parameters = [
            'publicationId' => $downloadRequest->getPublication()->getId(),
        ];
        if ($downloadRequest->getSubDefinition()) {
            $parameters['subDefId'] = $downloadRequest->getSubDefinition()->getId();
        } else {
            $parameters['assetId'] = $downloadRequest->getAsset()->getId();
        }
        $uri = $this->urlGenerator->generate($downloadRequest->getSubDefinition() ? 'download_subdef' : 'download_asset', $parameters, UrlGeneratorInterface::ABSOLUTE_URL);

        $downloadUrl = $this->JWTManager->signUri(
            $uri,
            259200 // 3 days
        );

        $this->notifier->sendEmail(
            $downloadRequest->getEmail(),
            'expose/download_link',
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
