<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;

class RequestResetPasswordHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'request_reset_password';

    public function handle(EventMessage $message): void
    {
        // TODO
        //echo $message->getType();
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
