<?php

declare(strict_types=1);

namespace App\Model;

enum AutomationRunStatusEnum: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Success = 'success';
    // Some assets failed: they stay "new" and are retried at the next run
    case Partial = 'partial';
    case Failed = 'failed';
    // Another run of the same automation was in progress
    case Skipped = 'skipped';
    case Cancelled = 'cancelled';

    public function isFinished(): bool
    {
        return !in_array($this, [self::Pending, self::Running], true);
    }

    public static function getChoices(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->name] = $case;
        }

        return $choices;
    }
}
