<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Scheduler;

use Symfony\Component\Scheduler\Trigger\AbstractDecoratedTrigger;
use Symfony\Component\Scheduler\Trigger\TriggerInterface;

/**
 * Shifts every run of the decorated trigger by a fixed number of seconds
 * (negative to run earlier).
 */
final class OffsetTrigger extends AbstractDecoratedTrigger
{
    public function __construct(
        private readonly TriggerInterface $trigger,
        private readonly int $seconds,
    ) {
        parent::__construct($trigger);
    }

    public function __toString(): string
    {
        return \sprintf('%s offset by %+d seconds', $this->trigger, $this->seconds);
    }

    public function getNextRunDate(\DateTimeImmutable $run): ?\DateTimeImmutable
    {
        // $run is an offset date: the decorated trigger expects its own
        return $this->trigger
            ->getNextRunDate($run->modify(\sprintf('%+d seconds', -$this->seconds)))
            ?->modify(\sprintf('%+d seconds', $this->seconds));
    }
}
