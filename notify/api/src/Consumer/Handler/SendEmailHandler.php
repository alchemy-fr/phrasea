<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use App\Mail\Mailer;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SendEmailHandler
{
    public function __construct(private Mailer $mailer)
    {
    }

    public function __invoke(SendEmail $message): void
    {
        $this->mailer->send(
            $message->getEmail(),
            $message->getTemplate(),
            $message->getParameters(),
            $message->getLocale()
        );
    }
}
