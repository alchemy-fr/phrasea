<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Tests\Scheduler;

use Alchemy\CoreBundle\Scheduler\OffsetTrigger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Trigger\CronExpressionTrigger;

class OffsetTriggerTest extends TestCase
{
    #[DataProvider('getCases')]
    public function testShiftsTheDecoratedRuns(int $offset, string $run, string $expected): void
    {
        $trigger = new OffsetTrigger(CronExpressionTrigger::fromSpec('0 * * * *'), $offset);

        $this->assertEquals(
            new \DateTimeImmutable($expected),
            $trigger->getNextRunDate(new \DateTimeImmutable($run)),
        );
    }

    public static function getCases(): array
    {
        return [
            'before the offset run' => [600, '2026-11-02 10:05:00', '2026-11-02 10:10:00'],
            'right on the offset run' => [600, '2026-11-02 10:10:00', '2026-11-02 11:10:00'],
            'between the hour and the offset run' => [600, '2026-11-02 10:00:00', '2026-11-02 10:10:00'],
            'longer than the period' => [5400, '2026-11-02 10:00:00', '2026-11-02 10:30:00'],
            'negative' => [-600, '2026-11-02 10:00:00', '2026-11-02 10:50:00'],
            'none' => [0, '2026-11-02 10:00:00', '2026-11-02 11:00:00'],
        ];
    }
}
