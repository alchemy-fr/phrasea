<?php

namespace App\Tests\Util;

use App\Util\DateUtil;
use PHPUnit\Framework\TestCase;

class DateUtilTest extends TestCase
{
    public function testNormalizeDateWithNull()
    {
        $this->assertNull(DateUtil::normalizeDate(null));
    }

    public function testNormalizeDateWithEmptyString()
    {
        $this->assertNull(DateUtil::normalizeDate(''));
    }

    public function testNormalizeDateWithDateTime()
    {
        $dt = new \DateTime('2024-01-01 12:00:00');
        $result = DateUtil::normalizeDate($dt);
        $this->assertInstanceOf(\DateTimeImmutable::class, $result);
        $this->assertEquals($dt->format(\DateTimeInterface::ATOM), $result->format(\DateTimeInterface::ATOM));
    }

    public function testNormalizeDateWithTimestamp()
    {
        $timestamp = 1704067200; // 2024-01-01T00:00:00+00:00
        $result = DateUtil::normalizeDate($timestamp);
        $this->assertInstanceOf(\DateTimeImmutable::class, $result);
        $this->assertSame((string) $timestamp, $result->format('U'));
    }

    public function testNormalizeDateWithDateString()
    {
        $result = DateUtil::normalizeDate('2024-01-02');
        $this->assertInstanceOf(\DateTimeImmutable::class, $result);
        $this->assertEquals('2024-01-02T00:00:00+00:00', $result->format('Y-m-d\TH:i:sP'));
    }

    public function testNormalizeDateWithDateTimeString()
    {
        $result = DateUtil::normalizeDate('2024-01-02T13:45');
        $this->assertInstanceOf(\DateTimeImmutable::class, $result);
        $this->assertEquals('2024-01-02T13:45:00+00:00', $result->format('Y-m-d\TH:i:sP'));
    }

    public function testNormalizeDateWithFullDateTimeString()
    {
        $result = DateUtil::normalizeDate('2024-01-02 13:45:59');
        $this->assertInstanceOf(\DateTimeImmutable::class, $result);
        $this->assertEquals('2024-01-02T13:45:59+00:00', $result->format('Y-m-d\TH:i:sP'));
    }

    public function testNormalizeDateWithInvalidString()
    {
        $this->assertNull(DateUtil::normalizeDate('not-a-date'));
    }

    public function testNormalizeDateWithUnsupportedType()
    {
        $this->assertNull(DateUtil::normalizeDate([]));
        $this->assertNull(DateUtil::normalizeDate(new \stdClass()));
    }
}
