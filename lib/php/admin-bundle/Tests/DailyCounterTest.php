<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Tests;

use Alchemy\AdminBundle\Home\DailyCounter;
use PHPUnit\Framework\TestCase;

class DailyCounterTest extends TestCase
{
    public function testGetDaysEndsToday(): void
    {
        $days = DailyCounter::getDays(3, new \DateTimeImmutable('2026-11-01 15:42:10'));

        $this->assertEquals([
            new \DateTimeImmutable('2026-10-30 00:00:00'),
            new \DateTimeImmutable('2026-10-31 00:00:00'),
            new \DateTimeImmutable('2026-11-01 00:00:00'),
        ], $days);
    }

    public function testGetDaysDefaultsToThirtyDays(): void
    {
        $days = DailyCounter::getDays(today: new \DateTimeImmutable('2026-11-25 08:00:00'));

        $this->assertCount(30, $days);
        $this->assertSame('2026-10-27', $days[0]->format('Y-m-d'));
        $this->assertSame('2026-11-25', $days[29]->format('Y-m-d'));
        foreach ($days as $i => $day) {
            $this->assertSame('00:00:00', $day->format('H:i:s'));
            if ($i > 0) {
                $this->assertSame($days[$i - 1]->modify('+1 day')->format('Y-m-d'), $day->format('Y-m-d'));
            }
        }
    }

    public function testGetDaysDefaultsToToday(): void
    {
        $days = DailyCounter::getDays(1);

        $this->assertCount(1, $days);
        $this->assertSame(new \DateTimeImmutable('today')->format('Y-m-d'), $days[0]->format('Y-m-d'));
    }

    public function testGetDaysRejectsAnEmptyRange(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        DailyCounter::getDays(0);
    }

    public function testFormatLabel(): void
    {
        $this->assertSame('05/03', DailyCounter::formatLabel(new \DateTimeImmutable('2026-03-05 23:59:59')));
    }
}
