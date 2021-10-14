<?php

declare(strict_types=1);

namespace App\Border\Consumer\Handler;

use App\Border\Model\Upload\IncomingUpload;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;

class NewUploaderCommitHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'new_uploader_commit';

    private EventProducer $eventProducer;

    public function __construct(EventProducer $eventProducer)
    {
        $this->eventProducer = $eventProducer;
    }

    public function handle(EventMessage $message): void
    {
        $incomingUpload = IncomingUpload::fromArray($message->getPayload());

        foreach ($incomingUpload->assets as $assetId) {
            $this->eventProducer->publish(new EventMessage(FileEntranceHandler::EVENT, [
                'assetId' => $assetId,
                'baseUrl' => $incomingUpload->base_url,
                'commitId' => $incomingUpload->commit_id,
                'userId' => $incomingUpload->publisher,
                'token' => $incomingUpload->token,
            ]));
        }
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
