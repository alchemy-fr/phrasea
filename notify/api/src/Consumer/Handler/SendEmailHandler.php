<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use App\Mail\Mailer;
use Symfony\Component\Mailer\Exception\TransportException;

class SendEmailHandler extends AbstractRetryableHandler
{
    public const EVENT = 'send_email';

    private Mailer $mailer;

    public function __construct(Mailer $mailer)
    {
        $this->mailer = $mailer;
    }

    protected function doHandle(array $payload): void
    {
        // TODO handle blacklist

        $this->mailer->send(
            $payload['email'],
            $payload['template'],
            $payload['parameters'],
            $payload['locale']
        );
    }

    protected function isRetryableException(\Throwable $e): bool
    {
        return $e instanceof TransportException;
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
