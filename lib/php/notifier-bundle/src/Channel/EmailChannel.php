<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Channel;

use Alchemy\NotifierBundle\Entity\Subscriber;
use Alchemy\NotifierBundle\Notification\RenderedContent;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final readonly class EmailChannel implements ChannelInterface
{
    public function __construct(
        private MailerInterface $mailer,
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
        RenderedContent $content,
        array $context = [],
        array $options = [],
    ): void {
        $email = (new Email())
            ->to($subscriber->getEmail())
            ->subject($content->subject ?? ($options['subject'] ?? $topic))
            ->html($content->body)
        ;

        if (isset($options['from'])) {
            $email->from($options['from']);
        }

        $this->mailer->send($email);
    }
}
