<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Channel;

use Alchemy\NotifierBundle\Entity\Subscriber;
use Alchemy\NotifierBundle\Notification\NotificationRenderer;
use Alchemy\NotifierBundle\Notification\RenderedContent;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final readonly class EmailChannel implements ChannelInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private NotificationRenderer $renderer,
    ) {
    }

    public function getType(): ChannelType
    {
        return ChannelType::Email;
    }

    public function supports(Subscriber $subscriber): bool
    {
        return null !== $subscriber->getEmail() && '' !== $subscriber->getEmail();
    }

    public function send(
        Subscriber $subscriber,
        string $topic,
        array $context = [],
        array $options = [],
    ): void {
        $content = $this->renderer->render($topic, ChannelType::Email, $context, $subscriber->getLocale());
        if (null === $content) {
            return;
        }

        $this->sendTo($subscriber->getEmail(), $topic, $content, $options);
    }

    /**
     * Sends a rendered notification to a raw email address (no subscriber).
     *
     * @param array<string, mixed> $options
     */
    public function sendTo(?string $email, string $topic, RenderedContent $content, array $options = []): void
    {
        if (null === $email || '' === $email) {
            return;
        }

        $message = (new Email())
            ->to($email)
            ->subject($content->subject ?? ($options['subject'] ?? $topic))
            ->html($content->body)
        ;

        if (isset($options['from'])) {
            $message->from($options['from']);
        }

        $this->mailer->send($message);
    }
}
