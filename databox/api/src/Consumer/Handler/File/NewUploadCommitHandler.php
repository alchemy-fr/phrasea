<?php

declare(strict_types=1);

namespace App\Consumer\Handler\File;

use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;

class NewUploadCommitHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'new_upload_commit';

    private EventProducer $eventProducer;

    public function __construct(EventProducer $eventProducer)
    {
        $this->eventProducer = $eventProducer;
    }

    public function handle(EventMessage $message): void
    {
        $payload = $message->getPayload();
        $files = $payload['files'] ?? [];

        foreach ($files as $file) {
            $this->eventProducer->publish();
        }
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
