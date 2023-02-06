<?php

declare(strict_types=1);

namespace App\Border\Consumer\Handler\Uploader;

use App\Border\Model\Upload\IncomingUpload;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;

class UploaderNewCommitHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'uploader_new_commit';

    private EventProducer $eventProducer;

    public function __construct(EventProducer $eventProducer)
    {
        $this->eventProducer = $eventProducer;
    }

    public function handle(EventMessage $message): void
    {
        $upload = IncomingUpload::fromArray($message->getPayload());

        foreach ($upload->assets as $assetId) {
            $this->eventProducer->publish(UploaderNewFileHandler::createEvent($assetId, $upload->base_url, $upload->token));
        }
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
