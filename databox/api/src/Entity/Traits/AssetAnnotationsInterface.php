<?php

declare(strict_types=1);

namespace App\Entity\Traits;

/**
 * Shapes drawn over an asset (a rendition). Coordinates and sizes are
 * relative to the rendered dimensions (0..1), so that the same annotation
 * fits every rendition. "page" (1-based) targets a page of a document.
 */
interface AssetAnnotationsInterface
{
    // "x", "y", "c"? (color in hexa), "s"? (size)
    final public const string TYPE_TARGET = 'target';

    // "x", "y", "r" (radius in %), "c"? (border color in hexa), "f"? (fill color in hexa), "s"? (stroke size)
    final public const string TYPE_CIRCLE = 'circle';

    // "x", "y", "w", "h", "c"? (border color in hexa), "f"? (fill color in hexa), "s"? (stroke size)
    final public const string TYPE_RECTANGLE = 'rect';

    // "x", "y", "w", "h" (box of the oval); no style option: the client draws it
    final public const string TYPE_FACE = 'face';

    // "x1", "y1", "x2", "y2", "c"? (color in hexa), "s"? (stroke size)
    final public const string TYPE_LINE = 'line';

    // Same as a line, with an arrow head on (x2, y2)
    final public const string TYPE_ARROW = 'arrow';

    // "paths" (array of arrays of {"x", "y"}), "c"? (color in hexa), "s"? (stroke size)
    final public const string TYPE_DRAW = 'draw';

    // "x", "y", "text", "c"? (color in hexa), "s"? (font size)
    final public const string TYPE_TEXT = 'text';

    // "t" (time: float in seconds)
    final public const string TYPE_CUE = 'cue';

    // "s" (start time: float in seconds), "e" (end time: float in seconds)
    final public const string TYPE_TIME_RANGE = 'time_range';

    final public const array TYPES = [
        self::TYPE_TARGET,
        self::TYPE_CIRCLE,
        self::TYPE_RECTANGLE,
        self::TYPE_FACE,
        self::TYPE_LINE,
        self::TYPE_ARROW,
        self::TYPE_DRAW,
        self::TYPE_TEXT,
        self::TYPE_CUE,
        self::TYPE_TIME_RANGE,
    ];
}
