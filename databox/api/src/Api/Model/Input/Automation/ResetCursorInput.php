<?php

declare(strict_types=1);

namespace App\Api\Model\Input\Automation;

use ApiPlatform\Metadata\ApiProperty;

class ResetCursorInput
{
    final public const string MODE_EMPTY = 'empty';
    final public const string MODE_CURRENT = 'current';

    /**
     * "empty": every result becomes new again;
     * "current": the current results are marked as handled, without running any action.
     */
    #[ApiProperty(jsonSchemaContext: [
        'type' => 'string',
        'enum' => [self::MODE_EMPTY, self::MODE_CURRENT],
    ])]
    public ?string $mode = null;
}
