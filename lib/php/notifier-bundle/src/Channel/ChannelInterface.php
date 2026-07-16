<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Channel;

use Alchemy\NotifierBundle\Entity\Subscriber;
use Alchemy\NotifierBundle\Notification\RenderedContent;

interface ChannelInterface
{
    public function getType(): ChannelType;

    /**
     * Whether the subscriber can receive a notification on this channel
     * (e.g. an email/phone is known).
     */
    public function supports(Subscriber $subscriber): bool;

    /**
     * @param array<string, mixed> $context The template parameters
     * @param array<string, mixed> $options Per-send options
     */
    public function send(
        Subscriber $subscriber,
        string $topic,
        RenderedContent $content,
        array $context = [],
        array $options = [],
    ): void;
}
