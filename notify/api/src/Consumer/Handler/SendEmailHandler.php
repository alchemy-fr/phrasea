<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use App\Mail\Mailer;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractLogHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;

class SendEmailHandler extends AbstractLogHandler
{
    const EVENT = 'send_email';

    /**
     * @var Mailer
     */
    private $mailer;

    public function __construct(Mailer $mailer)
    {
        $this->mailer = $mailer;
    }

    public function handle(EventMessage $message): void
    {
        $payload = $message->getPayload();

        // TODO handle blacklist

        $this->mailer->send(
            $payload['email'],
            $payload['template'],
            $payload['parameters'],
            $payload['locale']
        );
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
