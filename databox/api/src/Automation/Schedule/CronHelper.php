<?php

declare(strict_types=1);

namespace App\Automation\Schedule;

use Cron\CronExpression;

final readonly class CronHelper
{
    // Minimal interval between two occurrences of a schedule
    final public const int MIN_INTERVAL_SECONDS = 300;

    // Occurrences checked against the minimal interval
    private const int CHECKED_OCCURRENCES = 3;

    /**
     * @return string|null the reason why the expression is refused, null when valid
     */
    public function validate(string $expression, string $timezone = 'UTC', ?\DateTimeImmutable $from = null): ?string
    {
        if (!self::isValidTimezone($timezone)) {
            return sprintf('Invalid timezone "%s".', $timezone);
        }
        if (!CronExpression::isValidExpression($expression)) {
            return sprintf('Invalid cron expression "%s".', $expression);
        }

        $cron = new CronExpression($expression);
        try {
            $dates = $cron->getMultipleRunDates(self::CHECKED_OCCURRENCES + 1, self::toTimezone($from ?? new \DateTimeImmutable(), $timezone), false, false, $timezone);
        } catch (\RuntimeException $e) {
            return sprintf('The cron expression "%s" never runs.', $expression);
        }

        for ($i = 1; $i < count($dates); ++$i) {
            if ($dates[$i]->getTimestamp() - $dates[$i - 1]->getTimestamp() < self::MIN_INTERVAL_SECONDS) {
                return sprintf('The schedule runs too often: at most every %d minutes.', self::MIN_INTERVAL_SECONDS / 60);
            }
        }

        return null;
    }

    /**
     * The next occurrence strictly after the given date, in the default timezone (the one dates are stored in).
     */
    public function getNextRunAt(string $expression, string $timezone, ?\DateTimeImmutable $from = null): \DateTimeImmutable
    {
        $cron = new CronExpression($expression);
        $next = $cron->getNextRunDate(self::toTimezone($from ?? new \DateTimeImmutable(), $timezone), 0, false, $timezone);

        return \DateTimeImmutable::createFromInterface($next)->setTimezone(new \DateTimeZone(date_default_timezone_get()));
    }

    public static function isValidTimezone(string $timezone): bool
    {
        return in_array($timezone, \DateTimeZone::listIdentifiers(), true);
    }

    private static function toTimezone(\DateTimeImmutable $date, string $timezone): \DateTimeImmutable
    {
        return $date->setTimezone(new \DateTimeZone($timezone));
    }
}
