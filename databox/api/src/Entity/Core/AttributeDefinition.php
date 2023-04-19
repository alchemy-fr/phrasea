<?php

declare(strict_types=1);

namespace App\Entity\Core;

use ApiPlatform\Core\Annotation\ApiProperty;
use App\Attribute\Type\TextAttributeType;
use App\Elasticsearch\Mapping\IndexMappingUpdater;
use App\Entity\AbstractUuidEntity;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Entity\Traits\WorkspaceTrait;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\ChangeTrackingPolicy("DEFERRED_EXPLICIT")
 * @ORM\Entity(repositoryClass="App\Repository\Core\AttributeDefinitionRepository")
 * @ORM\Table(
 *     uniqueConstraints={
 *          @ORM\UniqueConstraint(name="uniq_attr_def_ws_name",columns={"workspace_id", "name"}),
 *          @ORM\UniqueConstraint(name="uniq_attr_def_ws_key",columns={"workspace_id", "key"}),
 *          @ORM\UniqueConstraint(name="uniq_attr_def_ws_slug",columns={"workspace_id", "slug"})
 *     },
 *     indexes={
 *       @ORM\Index(name="searchable_idx", columns={"searchable"}),
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
     * Override trait for annotation.
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Core\Workspace", inversedBy="attributeDefinitions")
     * @ORM\JoinColumn(nullable=false)
     * @Groups({"attributedef:index"})
     */
    protected ?Workspace $workspace = null;

    /**
     * @Groups({"attributedef:index", "attributedef:read", "attributedef:write"})
     * @ORM\ManyToOne(targetEntity="AttributeClass", inversedBy="definitions")
     * @ORM\JoinColumn(nullable=false)
     * @ApiProperty(security="is_granted('READ_ADMIN', object)")
     */
    protected ?AttributeClass $class = null;

    /**
     * @var Attribute[]
     * @ORM\OneToMany(targetEntity="App\Entity\Core\Attribute", mappedBy="definition", cascade={"remove"})
     */
    private ?DoctrineCollection $attributes = null;

    /**
     * @Groups({"asset:index", "asset:read", "attributedef:index", "attribute:index"})
     * @ORM\Column(type="string", length=100, nullable=false)
     */
    private ?string $name = null;

    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     * @Gedmo\Slug(fields={"name"}, style="lower", separator="", unique=false)
     */
    private ?string $slug = null;

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
    private bool $sortable = false;

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
     * @Groups({"attributedef:index"})
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $searchBoost = null;

    /**
     * Initialize attributes after asset creation; key=locale.
     *
     * @Groups({"attributedef:index"})
     * @ORM\Column(type="json", nullable=true)
     */
    private ?array $initialValues = null;

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

    /**
     * @Groups({"renddef:index", "renddef:read", "renddef:write"})
     * @ORM\Column(type="smallint", nullable=false)
     * @ApiProperty(security="is_granted('READ_ADMIN', object)")
     */
    private int $position = 0;

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

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): void
    {
        $this->slug = $slug;
    }

    public function getClass(): ?AttributeClass
    {
        return $this->class;
    }

    public function setClass(?AttributeClass $class): void
    {
        $this->class = $class;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getInitialValues(): ?array
    {
        return $this->initialValues;
    }

    public function setInitialValues(?array $initialValues): void
    {
        $this->initialValues = $initialValues;
        $this->normalizeInitialValues();
    }

    public function getInitialValuesAll(): ?string
    {
        return $this->initialValues[IndexMappingUpdater::NO_LOCALE] ?? null;
    }

    public function setInitialValuesAll(?string $initializer): void
    {
        $this->initialValues[IndexMappingUpdater::NO_LOCALE] = $initializer;
        $this->normalizeInitialValues();
    }

    private function normalizeInitialValues(): void
    {
        if (empty(array_filter($this->initialValues))) {
            $this->initialValues = null;
        }
    }

    public function isSortable(): bool
    {
        return $this->sortable;
    }

    public function setSortable(bool $sortable): void
    {
        $this->sortable = $sortable;
    }
}
