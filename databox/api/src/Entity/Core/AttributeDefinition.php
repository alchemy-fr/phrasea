<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Alchemy\CoreBundle\Entity\Traits\UpdatedAtTrait;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Api\Model\Input\AttributeDefinitionInput;
use App\Api\Model\Output\AttributeDefinitionOutput;
use App\Api\Provider\AttributeDefinitionCollectionProvider;
use App\Attribute\AttributeInterface;
use App\Attribute\Type\TextAttributeType;
use App\Controller\Core\AttributeDefinitionSortAction;
use App\Entity\Traits\ErrorDisableInterface;
use App\Entity\Traits\ErrorDisableTrait;
use App\Entity\Traits\WorkspaceTrait;
use App\Repository\Core\AttributeDefinitionRepository;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

#[ApiResource(
    shortName: 'attribute-definition',
    operations: [
        new Get(),
        new Delete(security: 'is_granted("DELETE", object)'),
        new Put(security: 'is_granted("EDIT", object)'),
        new Patch(security: 'is_granted("EDIT", object)'),
        new GetCollection(),
        new Post(securityPostDenormalize: 'is_granted("CREATE", object)'),
        new Post(
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
        'groups' => [self::GROUP_LIST],
    ],
    input: AttributeDefinitionInput::class,
    output: AttributeDefinitionOutput::class,
    security: 'is_granted("'.JwtUser::IS_AUTHENTICATED_FULLY.'")',
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
class AttributeDefinition extends AbstractUuidEntity implements \Stringable, ErrorDisableInterface
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    use WorkspaceTrait;
    use ErrorDisableTrait;
    final public const string GROUP_READ = 'attrdef:read';
    final public const string GROUP_LIST = 'attrdef:index';

    /**
     * Override trait for annotation.
     */
    #[ORM\ManyToOne(targetEntity: Workspace::class, inversedBy: 'attributeDefinitions')]
    #[ORM\JoinColumn(nullable: false)]
    protected ?Workspace $workspace = null;

    #[ORM\ManyToOne(targetEntity: AttributeClass::class, inversedBy: 'definitions')]
    #[ORM\JoinColumn(nullable: false)]
    #[ApiProperty(security: "is_granted('READ_ADMIN', object)")]
    protected ?AttributeClass $class = null;

    /**
     * @var Attribute[]
     */
    #[ORM\OneToMany(mappedBy: 'definition', targetEntity: Attribute::class, cascade: ['remove'])]
    private ?DoctrineCollection $attributes = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: false)]
    private ?string $name = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    #[Gedmo\Slug(fields: ['name'], style: 'lower', unique: false, separator: '')]
    private ?string $slug = null;

    /**
     * Apply this definition to files of this MIME type.
     * If null, applied to all files.
     */
    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $fileType = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: false)]
    private string $fieldType = TextAttributeType::NAME;

    #[ORM\Column(type: Types::STRING, length: AttributeEntity::TYPE_LENGTH, nullable: true)]
    private ?string $entityType = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: false)]
    private bool $searchable = true;

    #[ORM\Column(type: Types::BOOLEAN, nullable: false)]
    private bool $facetEnabled = false;

    #[ORM\Column(type: Types::BOOLEAN, nullable: false)]
    private bool $sortable = false;

    #[ORM\Column(type: Types::BOOLEAN, nullable: false)]
    private bool $translatable = false;

    #[ORM\Column(type: Types::BOOLEAN, nullable: false)]
    private bool $multiple = false;

    #[ORM\Column(type: Types::BOOLEAN, nullable: false)]
    private bool $allowInvalid = false;

    #[ORM\Column(type: Types::BOOLEAN, nullable: false)]
    private bool $suggest = false;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $searchBoost = null;

    /**
     * Initialize attributes after asset creation; key=locale.
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $initialValues = null;

    /**
     * Resolve this template (TWIG syntax) if no user value provided.
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $fallback = null;

    /**
     * Unique key by workspace. Used to prevent duplicates.
     */
    #[ORM\Column(type: Types::STRING, length: 150, nullable: true)]
    private ?string $key = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $labels = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: false)]
    #[ApiProperty(security: "is_granted('READ_ADMIN', object)")]
    private int $position = 0;

    #[ORM\Column(type: Types::BOOLEAN, nullable: false, options: ['default' => true])]
    private bool $enabled = true;

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
        $this->fallback[AttributeInterface::NO_LOCALE] = $fallback;
    }

    public function getFallbackAll(): ?string
    {
        return $this->fallback[AttributeInterface::NO_LOCALE] ?? null;
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
        if (null !== $name = $this->getName()) {
            return sprintf('%s (%s)', $name, $this->getWorkspace()->getName());
        }

        return $this->getId();
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
        return $this->initialValues[AttributeInterface::NO_LOCALE] ?? null;
    }

    public function setInitialValuesAll(?string $initializer): void
    {
        $this->initialValues[AttributeInterface::NO_LOCALE] = $initializer;
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

    public function getLabels(): ?array
    {
        return $this->labels;
    }

    public function setLabels(?array $labels): void
    {
        $this->labels = $labels;
    }

    public function isSuggest(): bool
    {
        return $this->suggest;
    }

    public function setSuggest(bool $suggest): void
    {
        $this->suggest = $suggest;
    }

    public function getEntityType(): ?string
    {
        return $this->entityType;
    }

    public function setEntityType(?string $entityType): void
    {
        $this->entityType = $entityType;
    }

    public function disableAfterErrors(): void
    {
        $this->setEnabled(false);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        if (!$this->enabled && $enabled) {
            $this->clearErrors();
        }

        $this->enabled = $enabled;
    }
}
