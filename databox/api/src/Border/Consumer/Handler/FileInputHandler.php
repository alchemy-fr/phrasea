<?php

declare(strict_types=1);

namespace App\Border\Consumer\Handler;

use App\Border\BorderManager;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;

class FileInputHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'file_entrance';

    private BorderManager $borderManager;
    private EventProducer $eventProducer;

    public function __construct(BorderManager $borderManager, EventProducer $eventProducer)
    {
        $this->borderManager = $borderManager;
        $this->eventProducer = $eventProducer;
    }

    public function handle(EventMessage $message): void
    {
        $file = $message->getPayload()['file'];

        if ($this->borderManager->acceptFile($file)) {
            $this->eventProducer->publish(new EventMessage());
        } else {
            // TODO place into quarantine
        }
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
