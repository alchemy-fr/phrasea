<?php

namespace App\Entity\Traits;

interface AssetAnnotationsInterface
{
    // "x", "y", "s"? (point size in pixel), "c"? (border color in hexa)
    final public const TYPE_POINT = 'point';

    // "x", "y", "d" (diameter), "c"? (border color in hexa), "f"? (fill color in hexa), "b"? (border size in pixel)
    final public const TYPE_CIRCLE = 'circle';

    // "x1", "y1", "x2", "y2", "c"? (border color in hexa), "f"? (fill color in hexa), "b"? (border size in pixel)
    final public const TYPE_RECTANGLE = 'rect';

    // "t" (time: float in seconds)
    final public const TYPE_CUE = 'cue';

    // "s" (start time: float in seconds), "e" (end time: float in seconds)
    final public const TYPE_TIME_RANGE = 'time_range';

    final public const TYPES = [
        self::TYPE_POINT,
        self::TYPE_CIRCLE,
        self::TYPE_RECTANGLE,
        self::TYPE_CUE,
    ];

}
