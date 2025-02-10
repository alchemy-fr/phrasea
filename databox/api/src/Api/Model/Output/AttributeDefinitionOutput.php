<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use ApiPlatform\Metadata\ApiProperty;
use App\Api\Model\Output\Traits\CreatedAtDTOTrait;
use App\Api\Model\Output\Traits\UpdatedAtDTOTrait;
use App\Attribute\Type\TextAttributeType;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeClass;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\Share;
use App\Entity\Core\Workspace;
use Symfony\Component\Serializer\Annotation\Groups;

class AttributeDefinitionOutput extends AbstractUuidOutput
{
    use CreatedAtDTOTrait;
    use UpdatedAtDTOTrait;

    #[Groups([AttributeDefinition::GROUP_LIST])]
    public ?Workspace $workspace = null;

    #[Groups([AttributeDefinition::GROUP_LIST])]
    public bool $enabled = true;

    #[Groups([AttributeDefinition::GROUP_LIST, AttributeDefinition::GROUP_READ, AttributeDefinition::GROUP_WRITE])]
    #[ApiProperty(security: "is_granted('READ_ADMIN', object)")]
    public ?AttributeClass $class = null;

    #[Groups([Asset::GROUP_LIST, Asset::GROUP_READ, AttributeDefinition::GROUP_LIST, Attribute::GROUP_LIST, Share::GROUP_PUBLIC_READ])]
    public ?string $name = null;

    #[Groups([Asset::GROUP_LIST, Asset::GROUP_READ, AttributeDefinition::GROUP_LIST, Attribute::GROUP_LIST])]
    public ?string $slug = null;

    #[Groups([AttributeDefinition::GROUP_LIST])]
    public ?string $fileType = null;

    #[Groups([AttributeDefinition::GROUP_LIST, Asset::GROUP_LIST, Share::GROUP_PUBLIC_READ])]
    public string $fieldType = TextAttributeType::NAME;

    #[Groups([AttributeDefinition::GROUP_LIST])]
    public ?string $entityType = null;

    #[Groups([AttributeDefinition::GROUP_LIST])]
    public bool $searchable = true;

    #[Groups([AttributeDefinition::GROUP_LIST])]
    public bool $suggest = false;

    #[Groups([AttributeDefinition::GROUP_LIST])]
    public bool $facetEnabled = false;

    #[Groups([AttributeDefinition::GROUP_LIST, Asset::GROUP_LIST, Share::GROUP_PUBLIC_READ])]
    public bool $translatable = false;

    #[Groups([AttributeDefinition::GROUP_LIST, Asset::GROUP_LIST, Share::GROUP_PUBLIC_READ])]
    public bool $multiple = false;

    #[Groups([AttributeDefinition::GROUP_LIST])]
    public bool $allowInvalid = false;

    #[Groups([AttributeDefinition::GROUP_LIST])]
    public ?int $searchBoost = null;

    /**
     * Resolve this template (TWIG syntax) if no user value provided.
     */
    #[Groups([AttributeDefinition::GROUP_LIST])]
    public ?array $fallback = null;

    /**
     * To create initial attribute value(s) (tag name or twig template).
     */
    #[Groups([AttributeDefinition::GROUP_LIST])]
    public ?array $initialValues = null;

    /**
     * Unique key by workspace. Used to prevent duplicates.
     */
    public ?string $key = null;

    #[Groups([AttributeDefinition::GROUP_READ])]
    public ?array $labels = null;

    #[Groups([AttributeDefinition::GROUP_LIST])]
    public int $position = 0;

    #[Groups([AttributeDefinition::GROUP_LIST])]
    public ?bool $canEdit = null;

    #[Groups([AttributeDefinition::GROUP_LIST, Share::GROUP_PUBLIC_READ])]
    public function getLocales(): ?array
    {
        if ($this->translatable) {
            return $this->workspace->getEnabledLocales();
        }

        return null;
    }
}
