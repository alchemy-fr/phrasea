<?php

declare(strict_types=1);

namespace Alchemy\Workflow\State;

class StateUtil
{
    public static function getFormattedDuration(?float $duration): string
    {
        if (null === $duration) {
            return '-';
        }

        $negative = $duration < 0 ? '-' : '';
        $duration = abs($duration);

        $h = floor($duration / 3600);
        $m = floor($duration / 60) % 60;
        $s = floor($duration) % 60;
        $micro = floor(($duration - floor($duration)) * 1000);

        if ($h > 0) {
            return sprintf('%s%02dh%02dm%02ds', $negative, $h, $m, $s);
        }
        if ($m > 0) {
            return sprintf('%s%02dm%02ds', $negative, $m, $s);
        }

        if ($duration < 10) {
            return sprintf('%s%d.%03ds', $negative, $s, $micro);
        }

        return sprintf('%s%ds', $negative, $s);
    }
}
