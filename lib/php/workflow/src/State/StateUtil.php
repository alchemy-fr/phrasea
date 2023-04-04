<?php

declare(strict_types=1);

namespace Alchemy\Workflow\State;

class StateUtil
{
    public static function getFormattedDuration(?int $duration): string
    {
        if (null === $duration) {
            return '-';
        }

        $h = floor($duration / 3600);
        $m = floor(($duration / 60) % 60);
        $s = $duration % 60;

        if ($h > 0) {
            return sprintf('%02dh%02dm%02ds', $h, $m, $s);
        }
        if ($m > 0) {
            return sprintf('%02dh%02dm%02ds', $h, $m, $s);
        }

        return sprintf('%ds', $s);
    }
}
