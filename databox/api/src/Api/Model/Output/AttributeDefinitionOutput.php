<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\Api\Model\Output\Traits\CreatedAtDTOTrait;
use App\Api\Model\Output\Traits\UpdatedAtDTOTrait;
use App\Attribute\Type\TextAttributeType;
use App\Entity\Core\AttributeClass;
use App\Entity\Core\Workspace;
use Symfony\Component\Serializer\Annotation\Groups;


class AttributeDefinitionOutput extends AbstractUuidOutput
{
    use CreatedAtDTOTrait;
    use UpdatedAtDTOTrait;

    #[Groups(['attributedef:index'])]
    public ?Workspace $workspace = null;

    #[Groups(['attributedef:index', 'attributedef:read', 'attributedef:write'])]
    #[ApiProperty(security: "is_granted('READ_ADMIN', object)")]
    public ?AttributeClass $class = null;

    #[Groups(['asset:index', 'asset:read', 'attributedef:index', 'attribute:index'])]
    public ?string $name = null;

    #[Groups(['asset:index', 'asset:read', 'attributedef:index', 'attribute:index'])]
    public ?string $slug = null;

    #[Groups(['attributedef:index'])]
    public ?string $fileType = null;

    #[Groups(['attributedef:index', 'asset:index'])]
    public string $fieldType = TextAttributeType::NAME;

    #[Groups(['attributedef:index'])]
    public bool $searchable = true;

    #[Groups(['attributedef:index'])]
    public bool $facetEnabled = false;

    #[Groups(['attributedef:index'])]
    public bool $translatable = false;

    #[Groups(['attributedef:index'])]
    public bool $multiple = false;

    #[Groups(['attributedef:index'])]
    public bool $allowInvalid = false;

    #[Groups(['attributedef:index'])]
    public ?int $searchBoost = null;

    /**
     * Resolve this template (TWIG syntax) if no user value provided.
     */
    #[Groups(['attributedef:index'])]
    public ?array $fallback = null;

    /**
     * To create initial attribute value(s) (tag name or twig template).
     */
    #[Groups(['attributedef:index'])]
    public ?array $initialValues = null;

    /**
     * Unique key by workspace. Used to prevent duplicates.
     */
    public ?string $key = null;

    #[Groups(['attributedef:index'])]
    public ?bool $canEdit = null;

    #[Groups(['attributedef:index'])]
    public function getLocales(): ?array
    {
        if ($this->translatable) {
            return $this->workspace->getEnabledLocales();
        }

        return null;
    }
}
