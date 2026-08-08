<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Channel;

use Alchemy\NotifierBundle\Entity\Subscriber;
use Alchemy\NotifierBundle\Notification\NotificationRenderer;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\TexterInterface;

final readonly class SmsChannel implements ChannelInterface
{
    /**
     * The texter is optional: without a configured symfony/notifier texter
     * transport, the SMS channel stays inactive instead of breaking the container.
     */
    public function __construct(
        private NotificationRenderer $renderer,
        private ?TexterInterface $texter = null,
    ) {
    }

    public function getType(): ChannelType
    {
        return ChannelType::Sms;
    }

    public function supports(Subscriber $subscriber): bool
    {
        return null !== $this->texter
            && null !== $subscriber->getPhoneNumber()
            && '' !== $subscriber->getPhoneNumber();
    }

    public function send(
        Subscriber $subscriber,
        string $topic,
        array $context = [],
        array $options = [],
    ): void {
        if (null === $this->texter) {
            return;
        }

        $content = $this->renderer->render($topic, ChannelType::Sms, $context, $subscriber->getLocale());
        if (null === $content) {
            return;
        }

        $this->texter->send(new SmsMessage($subscriber->getPhoneNumber(), $content->body));
    }
}
