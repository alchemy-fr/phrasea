<?php

declare(strict_types=1);

namespace App\Api\Model\Input\Attribute;

abstract class AbstractBaseAttributeInput
{
    /**
     * @var string|float|int|bool|array|null
     */
    public $value;

    /**
     * @var string|null
     */
    public $locale;

    /**
     * @var int|null
     */
    public $position;

    /**
     * Target definition by name. Or use $definitionId.
     */
    public ?string $name = null;

    public ?string $definitionId = null;
}
