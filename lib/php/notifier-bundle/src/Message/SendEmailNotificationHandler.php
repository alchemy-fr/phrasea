<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Message;

use Alchemy\NotifierBundle\Channel\ChannelType;
use Alchemy\NotifierBundle\Channel\EmailChannel;
use Alchemy\NotifierBundle\Notification\NotificationRenderer;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SendEmailNotificationHandler
{
    public function __construct(
        private NotificationRenderer $renderer,
        private EmailChannel $emailChannel,
    ) {
    }

    public function __invoke(SendEmailNotification $message): void
    {
        $context = $message->params + [
            'recipient' => [
                'email' => $message->email,
                'locale' => $message->locale,
            ],
        ];

        $rendered = $this->renderer->render($message->topic, ChannelType::Email, $context, $message->locale);
        if (null === $rendered) {
            return;
        }

        $this->emailChannel->sendTo($message->email, $message->topic, $rendered);
    }
}
