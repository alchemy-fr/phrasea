<?php

declare(strict_types=1);

namespace App\Border\Consumer\Handler;

use ApiPlatform\Core\Api\IriConverterInterface;
use App\Border\Model\Upload\IncomingUpload;
use App\Border\UploaderClient;
use App\Entity\Core\Collection;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;

class NewUploaderCommitHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'new_uploader_commit';

    private EventProducer $eventProducer;
    private UploaderClient $uploaderClient;
    private IriConverterInterface $iriConverter;

    public function __construct(
        EventProducer $eventProducer,
        UploaderClient $uploaderClient,
        IriConverterInterface $iriConverter)
    {
        $this->eventProducer = $eventProducer;
        $this->uploaderClient = $uploaderClient;
        $this->iriConverter = $iriConverter;
    }

    public function handle(EventMessage $message): void
    {
        $upload = IncomingUpload::fromArray($message->getPayload());

        $commitData = $this->uploaderClient->getCommit($upload->base_url, $upload->commit_id, $upload->token);

        /** @var Collection $collection */
        $collection = $this->iriConverter->getItemFromIri($commitData['options']['destinations'][0]);

        // TODO denormalize and shoot events for each workspace destination

        foreach ($upload->assets as $assetId) {
            $this->eventProducer->publish(new EventMessage(FileEntranceHandler::EVENT, [
                'assetId' => $assetId,
                'baseUrl' => $upload->base_url,
                'commitId' => $upload->commit_id,
                'userId' => $upload->publisher,
                'token' => $upload->token,
                'workspaceId' => $collection->getWorkspace()->getId(),
                'destinations' => [
                    $collection->getId(),
                ],
            ]));
        }
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
