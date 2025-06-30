<?php

namespace App\Entity\Traits;

interface AssetAnnotationsInterface
{
    // "x", "y", "c"? (color in hexa)
    final public const string TYPE_TARGET = 'target';

    // "x", "y", "r" (radius in %), "c"? (border color in hexa), "f"? (fill color in hexa)
    final public const string TYPE_CIRCLE = 'circle';

    // "x", "y", "w", "h", "c"? (border color in hexa), "f"? (fill color in hexa)
    final public const string TYPE_RECTANGLE = 'rect';

    // "t" (time: float in seconds)
    final public const string TYPE_CUE = 'cue';

    // "s" (start time: float in seconds), "e" (end time: float in seconds)
    final public const string TYPE_TIME_RANGE = 'time_range';

    final public const array TYPES = [
        self::TYPE_TARGET,
        self::TYPE_CIRCLE,
        self::TYPE_RECTANGLE,
        self::TYPE_CUE,
    ];

}
