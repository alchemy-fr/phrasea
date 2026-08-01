<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Channel;

use Alchemy\NotifierBundle\Entity\Subscriber;

interface ChannelInterface
{
    public function getType(): ChannelType;

    /**
     * Whether the subscriber can receive a notification on this channel
     * (e.g. an email/phone is known).
     */
    public function supports(Subscriber $subscriber): bool;

    /**
     * Delivers the notification. Each channel renders (or stores) the raw
     * context on its own — the in-app channel, for instance, persists the
     * topic + payload and defers rendering to read time.
     *
     * @param array<string, mixed> $context The template parameters
     * @param array<string, mixed> $options Per-send options
     */
    public function send(
        Subscriber $subscriber,
        string $topic,
        array $context = [],
        array $options = [],
    ): void;
}
