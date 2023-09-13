<?php

declare(strict_types=1);

namespace App\Entity\Core;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Api\InputTransformer\AttributeDefinitionInputTransformer;
use App\Api\Model\Input\AttributeDefinitionInput;
use App\Api\Model\Output\AttributeDefinitionOutput;
use App\Api\Provider\AttributeDefinitionCollectionProvider;
use App\Attribute\Type\TextAttributeType;
use App\Controller\Core\AttributeDefinitionSortAction;
use App\Elasticsearch\Mapping\IndexMappingUpdater;
use App\Entity\AbstractUuidEntity;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Entity\Traits\WorkspaceTrait;
use App\Repository\Core\AttributeDefinitionRepository;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'attribute-definition',
    operations: [
        new Get(),
        new Delete(security: 'is_granted("DELETE", object)'),
        new Put(security: 'is_granted("EDIT", object)'),
        new Patch(security: 'is_granted("EDIT", object)'),
        new GetCollection(),
        new Post(securityPostDenormalize: 'is_granted("CREATE", object)'),
        new Put(
            uriTemplate: '/attribute-definitions/sort',
            controller: AttributeDefinitionSortAction::class,
            openapiContext: [
                'summary' => 'Reorder items',
                'description' => 'Reorder items',
                'requestBody' => [
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'description' => 'Ordered list of IDs',
                                'type' => 'array',
                                'items' => ['type' => 'string'],
                            ],
                        ],
                    ],
                ],
            ],
            input: false,
            output: false,
            name: 'put_sort'
        ),
    ],
    normalizationContext: [
        'groups' => [AttributeDefinition::GROUP_LIST],
    ],
    input: AttributeDefinitionInput::class,
    output: AttributeDefinitionOutput::class,
    security: 'is_granted("IS_AUTHENTICATED_FULLY")',
    provider: AttributeDefinitionCollectionProvider::class,
)]
#[ORM\Table]
#[ORM\Index(columns: ['searchable'], name: 'searchable_idx')]
#[ORM\Index(columns: ['field_type'], name: 'type_idx')]
#[ORM\UniqueConstraint(name: 'uniq_attr_def_ws_name', columns: ['workspace_id', 'name'])]
#[ORM\UniqueConstraint(name: 'uniq_attr_def_ws_key', columns: ['workspace_id', 'key'])]
#[ORM\UniqueConstraint(name: 'uniq_attr_def_ws_slug', columns: ['workspace_id', 'slug'])]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
#[ORM\Entity(repositoryClass: AttributeDefinitionRepository::class)]
class AttributeDefinition extends AbstractUuidEntity implements \Stringable
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    use WorkspaceTrait;
    final public const GROUP_READ = 'attrdef:read';
    final public const GROUP_LIST = 'attrdef:index';
    final public const GROUP_WRITE = 'attrdef:w';

    /**
     * Override trait for annotation.
     */
    #[ORM\ManyToOne(targetEntity: Workspace::class, inversedBy: 'attributeDefinitions')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups([AttributeDefinition::GROUP_LIST])]
    protected ?Workspace $workspace = null;

    #[Groups([AttributeDefinition::GROUP_LIST, AttributeDefinition::GROUP_READ, AttributeDefinition::GROUP_WRITE])]
    #[ORM\ManyToOne(targetEntity: AttributeClass::class, inversedBy: 'definitions')]
    #[ORM\JoinColumn(nullable: false)]
    #[ApiProperty(security: "is_granted('READ_ADMIN', object)")]
    protected ?AttributeClass $class = null;

    /**
     * @var Attribute[]
     */
    #[ORM\OneToMany(targetEntity: Attribute::class, mappedBy: 'definition', cascade: ['remove'])]
    private ?DoctrineCollection $attributes = null;

    #[Groups([Asset::GROUP_LIST, Asset::GROUP_READ, AttributeDefinition::GROUP_LIST, Attribute::GROUP_LIST])]
    #[ORM\Column(type: 'string', length: 100, nullable: false)]
    private ?string $name = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    #[Gedmo\Slug(fields: ['name'], style: 'lower', separator: '', unique: false)]
    private ?string $slug = null;

    /**
     * Apply this definition to files of this MIME type.
     * If null, applied to all files.
     */
    #[Groups([AttributeDefinition::GROUP_LIST])]
    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $fileType = null;

    #[Groups([AttributeDefinition::GROUP_LIST, Asset::GROUP_LIST])]
    #[ORM\Column(type: 'string', length: 50, nullable: false)]
    private string $fieldType = TextAttributeType::NAME;

    #[Groups([AttributeDefinition::GROUP_LIST])]
    #[ORM\Column(type: 'boolean', nullable: false)]
    private bool $searchable = true;

    #[Groups([AttributeDefinition::GROUP_LIST])]
    #[ORM\Column(type: 'boolean', nullable: false)]
    private bool $facetEnabled = false;

    #[Groups([AttributeDefinition::GROUP_LIST])]
    #[ORM\Column(type: 'boolean', nullable: false)]
    private bool $sortable = false;

    #[Groups([AttributeDefinition::GROUP_LIST])]
    #[ORM\Column(type: 'boolean', nullable: false)]
    private bool $translatable = false;

    #[Groups([AttributeDefinition::GROUP_LIST])]
    #[ORM\Column(type: 'boolean', nullable: false)]
    private bool $multiple = false;

    #[Groups([AttributeDefinition::GROUP_LIST])]
    #[ORM\Column(type: 'boolean', nullable: false)]
    private bool $allowInvalid = false;

    #[Groups([AttributeDefinition::GROUP_LIST])]
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $searchBoost = null;

    /**
     * Initialize attributes after asset creation; key=locale.
     */
    #[Groups([AttributeDefinition::GROUP_LIST])]
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $initialValues = null;

    /**
     * Resolve this template (TWIG syntax) if no user value provided.
     */
    #[Groups([AttributeDefinition::GROUP_LIST])]
    #[ORM\Column(type: 'array', nullable: true)]
    private ?array $fallback = null;

    /**
     * Unique key by workspace. Used to prevent duplicates.
     */
    #[ORM\Column(type: 'string', length: 150, nullable: true)]
    private ?string $key = null;

    #[Groups([RenditionDefinition::GROUP_LIST, RenditionDefinition::GROUP_READ, RenditionDefinition::GROUP_WRITE])]
    #[ORM\Column(type: 'smallint', nullable: false)]
    #[ApiProperty(security: "is_granted('READ_ADMIN', object)")]
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

    public function __toString(): string
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
