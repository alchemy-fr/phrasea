<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Tests\Scheduler;

use Alchemy\CoreBundle\Scheduler\OffsetScheduleProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;

class OffsetScheduleProviderTest extends TestCase
{
    public function testOffsetsEveryRecurringMessage(): void
    {
        $hourly = RecurringMessage::cron('0 * * * *', new \stdClass());
        $daily = RecurringMessage::cron('0 0 * * *', new \ArrayObject());
        $schedule = new Schedule()->add($hourly, $daily);

        $provider = new OffsetScheduleProvider($schedule, 600);
        $from = new \DateTimeImmutable('2026-11-02 10:30:00');

        // A Schedule is its own provider: a second call must not offset it twice.
        $provider->getSchedule();
        $recurringMessages = $provider->getSchedule()->getRecurringMessages();

        $this->assertCount(2, $recurringMessages);
        $this->assertEquals(new \DateTimeImmutable('2026-11-02 11:10:00'), $recurringMessages[0]->getTrigger()->getNextRunDate($from));
        $this->assertEquals(new \DateTimeImmutable('2026-11-03 00:10:00'), $recurringMessages[1]->getTrigger()->getNextRunDate($from));
        $this->assertSame($hourly->getProvider(), $recurringMessages[0]->getProvider());
        $this->assertSame($daily->getProvider(), $recurringMessages[1]->getProvider());
        $this->assertSame([$hourly, $daily], $schedule->getRecurringMessages());
    }

    public function testNoOffsetKeepsTheSchedule(): void
    {
        $schedule = new Schedule()->add(RecurringMessage::cron('0 * * * *', new \stdClass()));

        $this->assertSame($schedule, new OffsetScheduleProvider($schedule, 0)->getSchedule());
    }
}
