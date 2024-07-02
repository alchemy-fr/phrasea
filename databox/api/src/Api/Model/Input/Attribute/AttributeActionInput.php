<?php

declare(strict_types=1);

namespace App\Api\Model\Input\Attribute;

class AttributeActionInput extends AbstractExtendedAttributeInput
{
    /**
     * Attribute ID.
     */
    public ?string $id = null;

    /**
     * Attribute IDs.
     */
    public ?array $ids = null;

    /**
     * Asset IDs.
     *
     * @var string[]
     */
    public ?array $assets = null;

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
     * Regex flags (e.g. 'g').
     */
    public ?string $flags = null;

    public ?string $replaceWith = null;
}
