<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Phraseanet;

use App\Consumer\Handler\File\ImportRenditionHandler;
use App\Entity\Core\Asset;
use App\Entity\Core\File;
use App\Storage\RenditionManager;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Psr\Log\LoggerInterface;

class PhraseanetDownloadSubdefHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'phraseanet_download_subdef';

    private RenditionManager $renditionManager;
    private EventProducer $eventProducer;

    public function __construct(
        RenditionManager $renditionManager,
        EventProducer $eventProducer,
        LoggerInterface $logger
    ) {
        $this->renditionManager = $renditionManager;
        $this->eventProducer = $eventProducer;
        $this->logger = $logger;
    }

    public static function createEvent(string $assetId, string $databoxId, string $recordId, string $subdefName, string $permalink): EventMessage
    {
        $payload = [
            'id' => $assetId,
            'databoxId' => $databoxId,
            'recordId' => $recordId,
            'permalink' => $permalink,
            'name' => $subdefName,
        ];

        return new EventMessage(self::EVENT, $payload);
    }

    public function handle(EventMessage $message): void
    {
        $payload = $message->getPayload();
        $assetId = $payload['id'];
        $em = $this->getEntityManager();
        $asset = $em->find(Asset::class, $assetId);
        if (!$asset instanceof Asset) {
            return;
        }

        $this->logger->debug(sprintf('Handle subdef from Phraseanet for asset "%s"', $asset->getId()));

        $workspace = $asset->getWorkspace();
        $url = $payload['permalink'];

        [$urlPart] = explode('?', $url, 2);

        $rendition = $this->renditionManager->createOrReplaceRendition(
            $asset,
            $this->renditionManager->getRenditionDefinitionByName(
                $workspace,
                $payload['name']
            ),
            File::STORAGE_URL,
            $url,
            null,
            null,
            basename($urlPart)
        );

        $em->flush();

        $this->eventProducer->publish(ImportRenditionHandler::createEvent($rendition->getId()));
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
