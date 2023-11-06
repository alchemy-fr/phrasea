<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use App\Api\Model\Output\Traits\CreatedAtDTOTrait;
use App\Api\Model\Output\Traits\UpdatedAtDTOTrait;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Template\AssetDataTemplate;
use Symfony\Component\Serializer\Annotation\Groups;

class AttributeOutput extends AbstractUuidOutput
{
    use CreatedAtDTOTrait;
    use UpdatedAtDTOTrait;

    /**
     * @var Asset
     */
    #[Groups([Attribute::GROUP_LIST, Attribute::GROUP_READ])]
    public $asset;

    /**
     * Target definition by IRI. Or use $name.
     *
     * @var AttributeDefinition|null
     */
    #[Groups([Asset::GROUP_LIST, Asset::GROUP_READ, Attribute::GROUP_LIST, Attribute::GROUP_READ, AssetDataTemplate::GROUP_READ])]
    public $definition;

    #[Groups([Asset::GROUP_LIST, Asset::GROUP_READ, Attribute::GROUP_LIST, Attribute::GROUP_READ, AssetDataTemplate::GROUP_READ])]
    public $value;

    #[Groups([Asset::GROUP_LIST, Asset::GROUP_READ, Attribute::GROUP_LIST, Attribute::GROUP_READ])]
    public string|array|null $highlight;

    /**
     * Unique ID to group translations of the same attribute.
     */
    #[Groups([Attribute::GROUP_LIST, Attribute::GROUP_READ, AssetDataTemplate::GROUP_READ])]
    public ?string $translationId = null;

    /**
     * "human" or "machine".
     */
    #[Groups([Attribute::GROUP_LIST, Attribute::GROUP_READ])]
    public ?string $origin = null;

    #[Groups([Attribute::GROUP_LIST, Attribute::GROUP_READ])]
    public ?string $originVendor = null;

    #[Groups([Attribute::GROUP_LIST, Attribute::GROUP_READ])]
    public ?string $originUserId = null;

    /**
     * Could include vendor version, AI parameters, etc.
     */
    #[Groups([Attribute::GROUP_LIST, Attribute::GROUP_READ])]
    public ?string $originVendorContext = null;

    #[Groups([Attribute::GROUP_LIST, Attribute::GROUP_READ])]
    public ?string $coordinates = null;

    /**
     * @var string|null
     */
    #[Groups([Asset::GROUP_LIST, Asset::GROUP_READ, Attribute::GROUP_LIST, Attribute::GROUP_READ, AssetDataTemplate::GROUP_READ])]
    public $locale;

    /**
     * @var int
     */
    #[Groups([Attribute::GROUP_LIST, Attribute::GROUP_READ, AssetDataTemplate::GROUP_READ])]
    public $position;

    /**
     * @var string
     */
    #[Groups([Attribute::GROUP_LIST, Attribute::GROUP_READ])]
    public $status;

    #[Groups([Attribute::GROUP_LIST, Attribute::GROUP_READ])]
    public $confidence;

    /**
     * @var bool
     */
    #[Groups([Asset::GROUP_LIST, Asset::GROUP_READ, Attribute::GROUP_LIST, Attribute::GROUP_READ])]
    public $multiple;
}
