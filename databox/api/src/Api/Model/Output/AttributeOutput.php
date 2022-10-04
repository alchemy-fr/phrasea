<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use ApiPlatform\Core\Annotation\ApiProperty;
use App\Api\Model\Output\Traits\CreatedAtDTOTrait;
use App\Api\Model\Output\Traits\UpdatedAtDTOTrait;
use App\Entity\Core\Asset;
use App\Entity\Core\AttributeDefinition;
use Symfony\Component\Serializer\Annotation\Groups;

class AttributeOutput extends AbstractUuidOutput
{
    use CreatedAtDTOTrait;
    use UpdatedAtDTOTrait;

    /**
     * @var Asset
     * @Groups({"attribute:index", "attribute:read"})
     */
    public $asset;

    /**
     * Target definition by IRI. Or use $name.
     *
     * @var AttributeDefinition|null
     * @Groups({"asset:index", "asset:read", "attribute:index", "attribute:read"})
     */
    public $definition;

    /**
     * @ApiProperty(attributes={
     *    "json_schema_context"={"type"={"string", "number", "boolean", "array", "null"}},
     *     "openapi_context"={"type":null,"oneOf":{{"type":"string"},{"type":"number"},{"type":"boolean"},{"type":"array"}}},
     * })
     *
     * @var string|float|int|bool|array|null
     * @Groups({"asset:index", "asset:read", "attribute:index", "attribute:read"})
     */
    public $value;

    /**
     * @ApiProperty(attributes={
     *    "json_schema_context"={"type"={"string", "number", "boolean", "array", "null"}},
     *     "openapi_context"={"type":null,"oneOf":{{"type":"string"},{"type":"number"},{"type":"boolean"},{"type":"array"}}},
     * })
     *
     * @var string|float|int|bool|array|null
     * @Groups({"asset:index", "asset:read", "attribute:index", "attribute:read"})
     */
    public $fallbackValue;

    /**
     * @var array|string|null
     * @Groups({"asset:index", "asset:read", "attribute:index", "attribute:read"})
     */
    public $highlight;

    /**
     * Unique ID to group translations of the same attribute.
     *
     * @Groups({"attribute:index", "attribute:read"})
     */
    public ?string $translationId = null;

    /**
     * "human" or "machine".
     *
     * @var string
     * @Groups({"attribute:index", "attribute:read"})
     */
    public $origin;

    /**
     * @Groups({"attribute:index", "attribute:read"})
     */
    public ?string $originVendor = null;

    /**
     * @Groups({"attribute:index", "attribute:read"})
     */
    public ?string $originUserId = null;

    /**
     * Could include vendor version, AI parameters, etc.
     *
     * @Groups({"attribute:index", "attribute:read"})
     */
    public ?string $originVendorContext = null;

    /**
     * @Groups({"attribute:index", "attribute:read"})
     */
    public ?string $coordinates = null;

    /**
     * @var string|null
     * @Groups({"asset:index", "asset:read", "attribute:index", "attribute:read"})
     */
    public $locale;

    /**
     * @var int
     * @Groups({"attribute:index", "attribute:read"})
     */
    public $position;

    /**
     * @var string
     * @Groups({"attribute:index", "attribute:read"})
     */
    public $status;

    /**
     * @Groups({"attribute:index", "attribute:read"})
     */
    public $confidence;
}
