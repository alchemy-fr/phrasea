<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Channel;

use Alchemy\NotifierBundle\Entity\Subscriber;
use Alchemy\NotifierBundle\Notification\RenderedContent;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\TexterInterface;

final readonly class SmsChannel implements ChannelInterface
{
    public function __construct(
        private TexterInterface $texter,
    ) {
    }

    public function getType(): ChannelType
    {
        return ChannelType::Sms;
    }

    public function supports(Subscriber $subscriber): bool
    {
        return null !== $subscriber->getPhoneNumber() && '' !== $subscriber->getPhoneNumber();
    }

    public function send(
        Subscriber $subscriber,
        string $topic,
        RenderedContent $content,
        array $context = [],
        array $options = [],
    ): void {
        $this->texter->send(new SmsMessage($subscriber->getPhoneNumber(), $content->body));
    }
}
