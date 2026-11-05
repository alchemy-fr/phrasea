<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Scheduler;

use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * Decorates every schedule provider of the application (see SchedulerOffsetPass)
 * to shift all its recurring messages by SCHEDULER_OFFSET seconds.
 */
final readonly class OffsetScheduleProvider implements ScheduleProviderInterface
{
    public function __construct(
        private ScheduleProviderInterface $inner,
        private int $offset,
    ) {
    }

    public function getSchedule(): Schedule
    {
        $schedule = $this->inner->getSchedule();
        if (0 === $this->offset) {
            return $schedule;
        }

        $recurringMessages = $schedule->getRecurringMessages();

        // A Schedule service is its own provider: rewriting it in place would offset it again on every call.
        $schedule = clone $schedule;
        $schedule->clear();
        foreach ($recurringMessages as $recurringMessage) {
            $schedule->add(RecurringMessage::trigger(
                new OffsetTrigger($recurringMessage->getTrigger(), $this->offset),
                $recurringMessage->getProvider(),
            ));
        }

        return $schedule;
    }
}
