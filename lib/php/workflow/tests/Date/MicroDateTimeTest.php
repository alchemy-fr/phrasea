<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Tests\Date;

use Alchemy\Workflow\Date\MicroDateTime;
use Alchemy\Workflow\State\StateUtil;
use PHPUnit\Framework\TestCase;

class MicroDateTimeTest extends TestCase
{
    /**
     * @dataProvider getCases
     */
    public function testDuration(string $expectedDiff, string $d1, int $nano1, string $d2, int $nano2): void
    {
        $d1 = new MicroDateTime($d1, $nano1);
        $d2 = new MicroDateTime($d2, $nano2);

        $this->assertEquals($expectedDiff, StateUtil::getFormattedDuration($d2->getDiff($d1)));
    }

    public function getCases(): array
    {
        return [
            ['01h00m00s', '2023-04-12 12:42:45', 0, '2023-04-12 13:42:45', 0],
            ['01h01m00s', '2023-04-12 12:42:45', 0, '2023-04-12 13:43:45', 0],
            ['01h01m01s', '2023-04-12 12:42:45', 0, '2023-04-12 13:43:46', 0],
            ['01m00s', '2023-04-12 12:42:45', 0, '2023-04-12 12:43:45', 0],
            ['01m01s', '2023-04-12 12:42:45', 0, '2023-04-12 12:43:46', 0],
            ['59s', '2023-04-12 12:42:45', 0, '2023-04-12 12:43:44', 0],
            ['0.000s', '2023-04-12 12:42:45', 1, '2023-04-12 12:42:45', 2],
            ['0.999s', '2023-04-12 12:42:45', 100, '2023-04-12 12:42:46', 2],
            ['-0.000s', '2023-04-12 12:42:45', 100, '2023-04-12 12:42:45', 2],
            ['0.999s', '2023-04-12 12:42:45', 0, '2023-04-12 12:42:45', 999999],
            ['-0.999s', '2023-04-12 12:42:45', 999999, '2023-04-12 12:42:45', 0],
            ['-0.000s', '2023-04-12 12:42:45', 1, '2023-04-12 12:42:45', 0],
            ['-0.000s', '2023-04-12 12:42:45', 10, '2023-04-12 12:42:45', 0],
            ['-0.000s', '2023-04-12 12:42:45', 100, '2023-04-12 12:42:45', 0],
            ['-0.001s', '2023-04-12 12:42:45', 1000, '2023-04-12 12:42:45', 0],
            ['-0.010s', '2023-04-12 12:42:45', 10000, '2023-04-12 12:42:45', 0],
            ['-0.100s', '2023-04-12 12:42:45', 100000, '2023-04-12 12:42:45', 0],
            ['0.000s', '2023-04-12 12:42:44', 999999, '2023-04-12 12:42:45', 0],
            ['0.999s', '2023-04-12 12:42:44', 999, '2023-04-12 12:42:45', 0],
            ['0.999s', '2023-04-12 12:42:44', 1, '2023-04-12 12:42:45', 0],
            ['0.999s', '2023-04-12 12:42:44', 10, '2023-04-12 12:42:45', 0],
            ['0.999s', '2023-04-12 12:42:44', 100, '2023-04-12 12:42:45', 0],
            ['0.999s', '2023-04-12 12:42:44', 1000, '2023-04-12 12:42:45', 0],
            ['0.990s', '2023-04-12 12:42:44', 10000, '2023-04-12 12:42:45', 0],
            ['0.900s', '2023-04-12 12:42:44', 100000, '2023-04-12 12:42:45', 0],
            ['0.010s', '2023-04-12 12:42:45', 10000, '2023-04-12 12:42:45', 20000],
        ];
    }

    /**
     * @dataProvider getMicroDates
     */
    public function testMicroDateTimeSerialization(MicroDateTime $dateTime): void
    {
        $serialized = serialize($dateTime);
        $this->assertEquals(
            sprintf('O:%d:"%s":2:{s:1:"t";i:%d;s:1:"m";i:%d;}',
            strlen(MicroDateTime::class),
                MicroDateTime::class,
                $dateTime->getDateTimeObject()->getTimestamp(),
                $dateTime->getMicroseconds()
            ), $serialized);

        $this->assertEquals($dateTime, unserialize($serialized));
    }

    public function getMicroDates(): array
    {
        return [
            [new MicroDateTime('2023-04-12T12:42:43', 414243)],
            [new MicroDateTime('2023-04-12T12:42:43', 1)],
            [new MicroDateTime('2023-04-12T12:42:43', 0)],
            [new MicroDateTime('now', 0)],
            [new MicroDateTime()],
            [new MicroDateTime('2023-04-12T12:42:43')],
        ];
    }
}
