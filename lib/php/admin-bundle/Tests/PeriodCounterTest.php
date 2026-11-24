<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Tests;

use Alchemy\AdminBundle\Home\PeriodCounter;
use PHPUnit\Framework\TestCase;

class PeriodCounterTest extends TestCase
{
    public function testFromCallablePassesTheWindowBounds(): void
    {
        $now = new \DateTimeImmutable('2026-11-24 12:00:00');
        $calls = [];

        $counts = PeriodCounter::fromCallable(static function (?\DateTimeImmutable $since) use (&$calls): int {
            $calls[] = $since;

            return count($calls) * 10;
        }, $now);

        $this->assertSame(['total' => 10, 'last24h' => 20, 'last7d' => 30, 'last30d' => 40], $counts);
        $this->assertCount(4, $calls);
        $this->assertNull($calls[0]);
        $this->assertEquals(new \DateTimeImmutable('2026-11-23 12:00:00'), $calls[1]);
        $this->assertEquals(new \DateTimeImmutable('2026-11-17 12:00:00'), $calls[2]);
        $this->assertEquals(new \DateTimeImmutable('2026-10-25 12:00:00'), $calls[3]);
    }

    public function testFromCallableDefaultsToNow(): void
    {
        $calls = [];
        $before = new \DateTimeImmutable();
        PeriodCounter::fromCallable(static function (?\DateTimeImmutable $since) use (&$calls): int {
            $calls[] = $since;

            return 0;
        });
        $after = new \DateTimeImmutable();

        $this->assertNull($calls[0]);
        foreach ([1 => '-24 hours', 2 => '-7 days', 3 => '-30 days'] as $i => $modifier) {
            $this->assertGreaterThanOrEqual($before->modify($modifier), $calls[$i]);
            $this->assertLessThanOrEqual($after->modify($modifier), $calls[$i]);
        }
    }
}
