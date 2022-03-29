<?php

declare(strict_types=1);

namespace App\Entity\Core;

use App\Attribute\Type\TextAttributeType;
use App\Elasticsearch\Mapping\IndexMappingUpdater;
use App\Entity\AbstractUuidEntity;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Entity\Traits\WorkspaceTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass="App\Repository\Core\AttributeDefinitionRepository")
 * @ORM\Table(
 *     uniqueConstraints={
 *          @ORM\UniqueConstraint(name="uniq_attr_def_ws_name",columns={"workspace_id", "name"}),
 *          @ORM\UniqueConstraint(name="uniq_attr_def_ws_key",columns={"workspace_id", "key"})
 *     },
 *     indexes={
 *       @ORM\Index(name="public_searchable_idx", columns={"searchable", "public"}),
 *       @ORM\Index(name="searchable_idx", columns={"searchable"}),
 *       @ORM\Index(name="public_idx", columns={"public"}),
 *       @ORM\Index(name="type_idx", columns={"field_type"}),
 *     }
 * )
 */
class AttributeDefinition extends AbstractUuidEntity
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    use WorkspaceTrait;

    /**
     * Override trait for annotation
     * @ORM\ManyToOne(targetEntity="App\Entity\Core\Workspace", inversedBy="attributeDefinitions")
     * @ORM\JoinColumn(nullable=false)
     * @Groups({"attributedef:index"})
     */
    protected ?Workspace $workspace = null;

    /**
     * @Groups({"asset:index", "asset:read", "attributedef:index"})
     * @ORM\Column(type="string", length=100, nullable=false)
     */
    private ?string $name = null;

    /**
     * Apply this definition to files of this MIME type.
     * If null, applied to all files.
     *
     * @Groups({"attributedef:index"})
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    private ?string $fileType = null;

    /**
     * @Groups({"attributedef:index", "asset:index"})
     * @ORM\Column(type="string", length=50, nullable=false)
     */
    private string $fieldType = TextAttributeType::NAME;

    /**
     * Value can be manually set by user.
     *
     * @Groups({"attributedef:index"})
     * @ORM\Column(type="boolean")
     */
    private bool $editable = true;

    /**
     * @Groups({"attributedef:index"})
     * @ORM\Column(type="boolean", nullable=false)
     */
    private bool $searchable = true;

    /**
     * @Groups({"attributedef:index"})
     * @ORM\Column(type="boolean", nullable=false)
     */
    private bool $facetEnabled = false;

    /**
     * @Groups({"attributedef:index"})
     * @ORM\Column(type="boolean", nullable=false)
     */
    private bool $translatable = false;

    /**
     * @Groups({"attributedef:index"})
     * @ORM\Column(type="boolean", nullable=false)
     */
    private bool $multiple = false;

    /**
     * @Groups({"attributedef:index"})
     * @ORM\Column(type="boolean", nullable=false)
     */
    private bool $allowInvalid = false;

    /**
     * @ORM\Column(type="boolean", nullable=false)
     */
    private bool $public = true;

    /**
     * @Groups({"attributedef:index"})
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $searchBoost = null;

    /**
     * Resolve this template (TWIG syntax) if no user value provided.
     *
     * @Groups({"attributedef:index"})
     * @ORM\Column(type="array", nullable=true)
     */
    private ?array $fallback = null;

    /**
     * Unique key by workspace. Used to prevent duplicates.
     *
     * @ORM\Column(type="string", length=150, nullable=true)
     */
    private ?string $key = null;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getFileType(): ?string
    {
        return $this->fileType;
    }

    public function setFileType(?string $fileType): void
    {
        $this->fileType = $fileType;
    }

    public function getFieldType(): string
    {
        return $this->fieldType;
    }

    public function setFieldType(string $fieldType): void
    {
        $this->fieldType = $fieldType;
    }

    public function isEditable(): bool
    {
        return $this->editable;
    }

    public function setEditable(bool $editable): void
    {
        $this->editable = $editable;
    }

    public function getFallback(): ?array
    {
        return $this->fallback;
    }

    public function setFallback(?array $fallback): void
    {
        $this->fallback = $fallback;
    }

    public function setFallbackEN(?string $fallback): void
    {
        $this->fallback['en'] = $fallback;
    }

    public function setFallbackFR(?string $fallback): void
    {
        $this->fallback['fr'] = $fallback;
    }

    public function setFallbackAll(?string $fallback): void
    {
        $this->fallback[IndexMappingUpdater::NO_LOCALE] = $fallback;
    }

    public function getFallbackAll(): ?string
    {
        return $this->fallback[IndexMappingUpdater::NO_LOCALE] ?? null;
    }

    public function getFallbackEN(): ?string
    {
        return $this->fallback['en'] ?? null;
    }

    public function getFallbackFR(): ?string
    {
        return $this->fallback['fr'] ?? null;
    }

    public function getSearchFieldName(): string
    {
        return $this->getId();
    }

    public function __toString()
    {
        return $this->getName() ?? $this->getId();
    }

    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    public function setSearchable(bool $searchable): void
    {
        $this->searchable = $searchable;
    }

    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    public function setMultiple(bool $multiple): void
    {
        $this->multiple = $multiple;
    }

    public function isPublic(): bool
    {
        return $this->public;
    }

    public function setPublic(bool $public): void
    {
        $this->public = $public;
    }

    public function getSearchBoost(): ?int
    {
        return $this->searchBoost;
    }

    public function setSearchBoost(?int $searchBoost): void
    {
        $this->searchBoost = $searchBoost;
    }

    public function isTranslatable(): bool
    {
        return $this->translatable;
    }

    public function setTranslatable(bool $translatable): void
    {
        $this->translatable = $translatable;
    }

    public function isAllowInvalid(): bool
    {
        return $this->allowInvalid;
    }

    public function setAllowInvalid(bool $allowInvalid): void
    {
        $this->allowInvalid = $allowInvalid;
    }

    public function getKey(): ?string
    {
        return $this->key;
    }

    public function setKey(?string $key): void
    {
        $this->key = $key;
    }

    public function isFacetEnabled(): bool
    {
        return $this->facetEnabled;
    }

    public function setFacetEnabled(bool $facetEnabled): void
    {
        $this->facetEnabled = $facetEnabled;
    }
}
