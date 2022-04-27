<?php

declare(strict_types=1);

namespace App\Api\Model\Input\Attribute;

class AttributeActionInput
{
    /**
     * Attribute ID
     */
    public ?string $id = null;

    public ?string $definitionId = null;

    /**
     * Available actions:
     *  - "set"
     *  - "add"
     *  - "delete"
     *  - "replace"
     *
     * Default is "set"
     */
    public ?string $action = 'set';

    /**
     * @var string|array|int|bool|null
     */
    public $value;

    public ?bool $regex = null;

    /**
     * Regex flags (i.e. 'g')
     */
    public ?string $flags = null;

    public ?string $replaceWith = null;
}
