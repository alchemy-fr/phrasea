<?php

declare(strict_types=1);

namespace App\Api\Model\Input\Attribute;

class AttributeActionInput extends AbstractAttributeInput
{
    /**
     * Attribute ID.
     */
    public ?string $id = null;

    /**
     * Attribute definition name.
     */
    public ?string $name = null;

    public ?string $definitionId = null;

    /**
     * Available actions:
     *  - "set"
     *  - "add"
     *  - "delete"
     *  - "replace".
     *
     * Default is "set"
     */
    public ?string $action = 'set';

    public ?bool $regex = null;

    /**
     * Regex flags (i.e. 'g').
     */
    public ?string $flags = null;

    public ?string $replaceWith = null;
}
