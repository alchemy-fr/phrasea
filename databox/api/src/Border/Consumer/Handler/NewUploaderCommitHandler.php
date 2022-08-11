<?php

declare(strict_types=1);

namespace App\Border\Consumer\Handler;

use ApiPlatform\Core\Api\IriConverterInterface;
use App\Border\Model\Upload\IncomingUpload;
use App\Border\UploaderClient;
use App\Entity\Core\Collection;
use App\Entity\Core\Workspace;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use InvalidArgumentException;

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

        $destinations = [];
        if (isset($commitData['options']['destinations'])) {
            $destinations = $commitData['options']['destinations'];
        } elseif (isset($commitData['formData']['collection_destination'])) {
            $destinations = ['/collections/'.$commitData['formData']['collection_destination']];
        }

        if (empty($destinations)) {
            throw new InvalidArgumentException('No destination provided');
        }
        // TODO validate user has permission to write to destinations

        $workspaces = [];
        foreach ($destinations as $destination) {
            $destItem = $this->iriConverter->getItemFromIri($destination);
            if ($destItem instanceof Collection) {
                $w = $destItem->getWorkspace()->getId();
                if (!isset($workspaces[$w])) {
                    $workspaces[$w] = [];
                }
                $workspaces[$w][] = $destItem->getId();
            } elseif ($destItem instanceof Workspace) {
                $w = $destItem->getId();
                if (!isset($workspaces[$w])) {
                    $workspaces[$w] = [];
                }
            }
        }

        foreach ($workspaces as $wId => $collections) {
            foreach ($upload->assets as $assetId) {
                $this->eventProducer->publish(new EventMessage(FileEntranceHandler::EVENT, [
                    'assetId' => $assetId,
                    'baseUrl' => $upload->base_url,
                    'commitId' => $upload->commit_id,
                    'userId' => $upload->publisher,
                    'token' => $upload->token,
                    'workspaceId' => $wId,
                    'collections' => $collections,
                ]));
            }
        }
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
