<?php

declare(strict_types=1);

namespace App\Api\Model\Output\Template;

use ApiPlatform\Core\Annotation\ApiProperty;
use App\Api\Model\Output\AbstractUuidOutput;
use App\Api\Model\Output\AttributeOutput;
use App\Api\Model\Output\Traits\CapabilitiesDTOTrait;
use App\Api\Model\Output\Traits\CreatedAtDTOTrait;
use App\Api\Model\Output\Traits\UpdatedAtDTOTrait;
use Symfony\Component\Serializer\Annotation\Groups;

class AssetDataTemplateOutput extends AbstractUuidOutput
{
    use CreatedAtDTOTrait;
    use UpdatedAtDTOTrait;
    use CapabilitiesDTOTrait;

    #[Groups(['asset-data-template:index'])]
    #[ApiProperty(attributes: ['openapi_context' => ['type' => 'object', 'properties' => ['canEdit' => ['type' => 'boolean'], 'canDelete' => ['type' => 'boolean'], 'canEditPermissions' => ['type' => 'boolean']]], 'json_schema_context' => ['type' => 'object', 'properties' => ['canEdit' => 'boolean', 'canDelete' => 'boolean', 'canEditPermissions' => 'boolean']]])]
    protected array $capabilities = [];

    /**
     * @var AttributeOutput[]
     */
    #[Groups(['asset-data-template:read'])]
    public ?array $attributes = null;

    /**
     * Template name.
     */
    #[Groups(['asset-data-template:index'])]
    public ?string $name = null;

    #[Groups(['asset-data-template:read'])]
    public bool $public = false;

    #[Groups(['asset-data-template:read'])]
    public ?string $ownerId = null;

    /**
     * Asset title.
     */
    #[Groups(['asset-data-template:read'])]
    public ?string $title = null;

    #[Groups(['asset-data-template:read'])]
    public ?array $tags = null;

    #[Groups(['asset-data-template:index'])]
    public $collection;

    #[Groups(['asset-data-template:index'])]
    public ?int $privacy = null;

    #[Groups(['asset-data-template:read'])]
    public bool $includeCollectionChildren = false;
}
