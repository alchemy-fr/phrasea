<?php

declare(strict_types=1);

namespace App\Tests\Unit\Automation;

use App\Automation\Schedule\CronHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CronHelperTest extends TestCase
{
    #[DataProvider('getValidExpressions')]
    public function testValidExpressions(string $expression, string $timezone): void
    {
        $this->assertNull(new CronHelper()->validate($expression, $timezone));
    }

    public static function getValidExpressions(): array
    {
        return [
            'every 5 minutes' => ['*/5 * * * *', 'UTC'],
            'hourly' => ['15 * * * *', 'UTC'],
            'daily' => ['30 8 * * *', 'Europe/Paris'],
            'weekly' => ['0 18 * * 1', 'America/New_York'],
            'working days' => ['0 8 * * 1-5', 'UTC'],
            'macro' => ['@daily', 'UTC'],
        ];
    }

    #[DataProvider('getInvalidExpressions')]
    public function testInvalidExpressions(string $expression, string $timezone, string $expectedError): void
    {
        $error = new CronHelper()->validate($expression, $timezone);

        $this->assertNotNull($error);
        $this->assertStringContainsString($expectedError, $error);
    }

    public static function getInvalidExpressions(): array
    {
        return [
            'not a cron' => ['every day', 'UTC', 'Invalid cron expression'],
            'out of range' => ['61 * * * *', 'UTC', 'Invalid cron expression'],
            'every minute' => ['* * * * *', 'UTC', 'too often'],
            'every 2 minutes' => ['*/2 * * * *', 'UTC', 'too often'],
            'two close occurrences' => ['0,3 * * * *', 'UTC', 'too often'],
            'unknown timezone' => ['0 * * * *', 'Mars/Olympus', 'Invalid timezone'],
        ];
    }

    public function testNextRunIsComputedInTheTimezone(): void
    {
        $helper = new CronHelper();
        $from = new \DateTimeImmutable('2026-01-15 10:00:00', new \DateTimeZone('UTC'));

        // 08:30 in Paris (UTC+1 in winter) is 07:30 UTC: already passed, so tomorrow
        $next = $helper->getNextRunAt('30 8 * * *', 'Europe/Paris', $from);
        $this->assertSame('2026-01-16T07:30:00+00:00', $next->setTimezone(new \DateTimeZone('UTC'))->format(\DATE_ATOM));

        // 08:30 in New York (UTC-5) is 13:30 UTC: later today
        $next = $helper->getNextRunAt('30 8 * * *', 'America/New_York', $from);
        $this->assertSame('2026-01-15T13:30:00+00:00', $next->setTimezone(new \DateTimeZone('UTC'))->format(\DATE_ATOM));

        // Summer time
        $next = $helper->getNextRunAt('30 8 * * *', 'Europe/Paris', new \DateTimeImmutable('2026-07-15 10:00:00', new \DateTimeZone('UTC')));
        $this->assertSame('2026-07-16T06:30:00+00:00', $next->setTimezone(new \DateTimeZone('UTC'))->format(\DATE_ATOM));
    }

    public function testNextRunIsStrictlyAfter(): void
    {
        $from = new \DateTimeImmutable('2026-01-15 10:05:00', new \DateTimeZone('UTC'));

        $next = new CronHelper()->getNextRunAt('*/5 * * * *', 'UTC', $from);

        $this->assertSame('2026-01-15T10:10:00+00:00', $next->setTimezone(new \DateTimeZone('UTC'))->format(\DATE_ATOM));
    }

    public function testNextRunIsInTheDefaultTimezone(): void
    {
        $next = new CronHelper()->getNextRunAt('0 * * * *', 'Asia/Tokyo');

        $this->assertSame(date_default_timezone_get(), $next->getTimezone()->getName());
    }
}
