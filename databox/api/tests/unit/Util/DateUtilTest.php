<?php

declare(strict_types=1);

namespace App\Tests\Unit\Util;

use App\Util\DateUtil;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testNormalizeDateWithFractionalSeconds()
    {
        $result = DateUtil::normalizeDate('2026-04-26T00:00:00.000Z');
        $this->assertInstanceOf(\DateTimeImmutable::class, $result);
        $this->assertSame('2026-04-26T00:00:00+00:00', $result->format(\DateTimeInterface::ATOM));

        $result = DateUtil::normalizeDate('2026-04-26T10:11:12.5+02:00');
        $this->assertInstanceOf(\DateTimeImmutable::class, $result);
        $this->assertSame('2026-04-26T10:11:12+02:00', $result->format(\DateTimeInterface::ATOM));
        $this->assertSame(
            (new \DateTimeImmutable('2026-04-26T08:11:12Z'))->getTimestamp(),
            $result->getTimestamp()
        );

        $result = DateUtil::normalizeDate('2026-04-26T10:11:12.123456+02:00');
        $this->assertInstanceOf(\DateTimeImmutable::class, $result);
        $this->assertSame('2026-04-26T10:11:12.123456+02:00', $result->format('Y-m-d\\TH:i:s.uP'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function stringProvider(): iterable
    {
        yield 'ATOM' => ['2026-04-26T10:11:12+02:00', '2026-04-26T10:11:12.000000+02:00'];
        yield 'Z' => ['2026-04-26T10:11:12Z', '2026-04-26T10:11:12.000000+00:00'];
        yield 'basic offset' => ['2026-04-26T10:11:12+0200', '2026-04-26T10:11:12.000000+02:00'];
        yield 'fractional seconds without time zone' => ['2026-04-26T10:11:12.123', '2026-04-26T10:11:12.123000+00:00'];
        yield 'no seconds with time zone' => ['2026-04-26T10:11+02:00', '2026-04-26T10:11:00.000000+02:00'];
        yield 'no seconds, space' => ['2026-04-26 10:11', '2026-04-26T10:11:00.000000+00:00'];
        yield 'EXIF' => ['2026:04:26 10:11:12', '2026-04-26T10:11:12.000000+00:00'];
        yield 'EXIF with offset' => ['2026:04:26 10:11:12+02:00', '2026-04-26T10:11:12.000000+02:00'];
        yield 'EXIF SubSecDateTimeOriginal' => ['2026:04:26 10:11:12.50+02:00', '2026-04-26T10:11:12.500000+02:00'];
        yield 'more than 6 decimals' => ['2026-04-26T10:11:12.123456789Z', '2026-04-26T10:11:12.123456+00:00'];
        yield 'lowercase separators' => ['2026-04-26t10:11:12z', '2026-04-26T10:11:12.000000+00:00'];
    }

    #[DataProvider('stringProvider')]
    public function testNormalizeDateString(string $value, string $expected): void
    {
        $result = DateUtil::normalizeDate($value);
        $this->assertInstanceOf(\DateTimeImmutable::class, $result);
        $this->assertSame($expected, $result->format('Y-m-d\\TH:i:s.uP'));
    }

    public function testNormalizeDateWithNonExistingDate(): void
    {
        $this->assertNull(DateUtil::normalizeDate('2026-02-30'));
        $this->assertNull(DateUtil::normalizeDate('2026-00-12T00:00:00Z'));
        $this->assertNull(DateUtil::normalizeDate('2026-03-00T00:00:00.000+0200'));
        $this->assertNull(DateUtil::normalizeDate('2026-04-26T24:30:00Z'));
        $this->assertNull(DateUtil::normalizeDate('0000-01-01'));
    }

    public function testNormalizeDateWithInvalidString()
    {
        $this->assertNull(DateUtil::normalizeDate('not-a-date'));
        $this->assertNull(DateUtil::normalizeDate('0000-00-00T00:00:00+00:00'));
        $this->assertNull(DateUtil::normalizeDate('0000-00-00T00:00:00+99:00'));
        $this->assertNull(DateUtil::normalizeDate('2026-04-26T.000Z'));
        $this->assertNull(DateUtil::normalizeDate('0000-00-00T00:00:00.000Z'));
    }

    public function testNormalizeDateWithUnsupportedType()
    {
        $this->assertNull(DateUtil::normalizeDate([]));
        $this->assertNull(DateUtil::normalizeDate(new \stdClass()));
    }
}
