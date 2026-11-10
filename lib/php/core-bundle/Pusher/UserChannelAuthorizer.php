<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Pusher;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Per-user channel (`{prefix}{userIdentifier}`, e.g. in-app notifications): only its owner may subscribe.
 */
final readonly class UserChannelAuthorizer implements ChannelAuthorizerInterface
{
    public function __construct(
        private PusherManager $pusherManager,
        private string $channelPrefix = PusherManager::PRIVATE_PREFIX.'user-',
    ) {
    }

    public function supports(string $channel): bool
    {
        return str_starts_with($channel, $this->pusherManager->normalizeChannel($this->channelPrefix));
    }

    public function isGranted(string $channel, ?UserInterface $user): bool
    {
        if (null === $user) {
            return false;
        }

        $expected = $this->pusherManager->normalizeChannel($this->channelPrefix.$user->getUserIdentifier());

        return hash_equals($expected, $channel);
    }
}
