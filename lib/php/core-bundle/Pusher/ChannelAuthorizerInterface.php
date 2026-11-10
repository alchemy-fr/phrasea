<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Pusher;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Decides whether the current visitor may subscribe to a private Pusher/Sockudo channel.
 *
 * The first authorizer supporting a channel decides: the others are not consulted.
 */
#[AutoconfigureTag(self::TAG)]
interface ChannelAuthorizerInterface
{
    final public const string TAG = 'alchemy_core.pusher.channel_authorizer';

    public function supports(string $channel): bool;

    /**
     * @param UserInterface|null $user null for an anonymous visitor
     */
    public function isGranted(string $channel, ?UserInterface $user): bool;
}
