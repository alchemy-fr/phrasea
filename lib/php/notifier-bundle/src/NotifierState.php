<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Runtime on/off switch for notification delivery.
 *
 * Initialized from the `alchemy_notifier.enabled` config, but mutable so that
 * a request/message can temporarily suppress notifications (quiet context).
 */
final class NotifierState
{
    public function __construct(
        #[Autowire(param: 'alchemy_notifier.enabled')]
        private bool $enabled = true,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }
}
