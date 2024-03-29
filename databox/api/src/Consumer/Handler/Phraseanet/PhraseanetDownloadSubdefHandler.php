<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Phraseanet;

use App\Consumer\Handler\File\ImportFileHandler;
use App\Entity\Core\Asset;
use App\Entity\Core\File;
use App\Storage\RenditionManager;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Psr\Log\LoggerInterface;

class PhraseanetDownloadSubdefHandler extends AbstractEntityManagerHandler
{
    final public const EVENT = 'phraseanet_download_subdef';

    public function __construct(
        private readonly RenditionManager $renditionManager,
        private readonly EventProducer $eventProducer,
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
    }

    public static function createEvent(
        string $assetId,
        string $databoxId,
        string $recordId,
        string $subdefName,
        string $permalink,
        ?string $type,
        ?int $size
    ): EventMessage {
        $payload = [
            'id' => $assetId,
            'databoxId' => $databoxId,
            'recordId' => $recordId,
            'permalink' => $permalink,
            'name' => $subdefName,
            'type' => $type,
            'size' => $size,
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
        if (empty($url)) {
            throw new \InvalidArgumentException(sprintf('Empty Phraseanet permalink'));
        }

        [$urlPart] = explode('?', (string) $url, 2);

        try {
            $renditionDefinition = $this->renditionManager->getRenditionDefinitionByName(
                $workspace,
                $payload['name']
            );
        } catch (\InvalidArgumentException $e) {
            $this->logger->warning($e->getMessage());

            return;
        }

        $rendition = $this->renditionManager->createOrReplaceRenditionByPath(
            $asset,
            $renditionDefinition,
            File::STORAGE_URL,
            $url,
            $payload['type'] ?? null,
            $payload['size'] ?? null,
            basename($urlPart)
        );

        $em->flush();

        $this->eventProducer->publish(ImportFileHandler::createEvent($rendition->getFile()->getId()));
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
