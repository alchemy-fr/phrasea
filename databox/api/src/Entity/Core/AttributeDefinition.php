<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Alchemy\CoreBundle\Entity\Traits\UpdatedAtTrait;
use Alchemy\TrackBundle\LoggableChangeSetInterface;
use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\QueryParameter;
use App\Api\Filter\AssetTypeTargetFilter;
use App\Api\Filter\InWorkspacesFilter;
use App\Api\Filter\SearchFilter;
use App\Api\Model\Input\AttributeDefinitionInput;
use App\Api\Model\Output\AttributeDefinitionOutput;
use App\Attribute\AttributeInterface;
use App\Attribute\Type\TextAttributeType;
use App\Controller\Core\AttributeDefinitionSortAction;
use App\Entity\Traits\AssetTypeTargetTrait;
use App\Entity\Traits\ErrorDisableInterface;
use App\Entity\Traits\ErrorDisableTrait;
use App\Entity\Traits\TranslationsTrait;
use App\Entity\Traits\WorkspaceTrait;
use App\Repository\Core\AttributeDefinitionRepository;
use App\Security\Voter\AbstractVoter;
use App\Validator\SameWorkspaceConstraint;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ApiResource(
    shortName: 'attribute-definition',
    operations: [
        new Get(
            security: 'is_granted("'.AbstractVoter::READ.'", object)',
        ),
        new Delete(security: 'is_granted("DELETE", object)'),
        new Put(
            security: 'is_granted("'.AbstractVoter::EDIT.'", object)'
        ),
        new Patch(security: 'is_granted("'.AbstractVoter::EDIT.'", object)'),
        new GetCollection(
            order: ['workspace' => 'ASC', 'position' => 'ASC', 'name' => 'ASC'],
            normalizationContext: [
                'groups' => [AttributeDefinition::GROUP_LIST],
            ],
            parameters: [
                'searchable' => new QueryParameter(
                    filter: BooleanFilter::class,
                    property: 'searchable',
                ),
                'workspaceId' => new QueryParameter(
                    filter: SearchFilter::class, property: 'workspace'),
                'workspaceIds' => new QueryParameter(
                    filter: InWorkspacesFilter::class,
                    property: 'workspace',
                ),
                'target' => new QueryParameter(
                    filter: AssetTypeTargetFilter::class,
                    property: 'target',
                ),
            ],
        ),
        new Post(
            security: 'is_granted("'.JwtUser::IS_AUTHENTICATED_FULLY.'")',
            securityPostDenormalize: 'is_granted("CREATE", object)',
        ),
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
            security: 'is_granted("'.JwtUser::IS_AUTHENTICATED_FULLY.'")',
            input: false,
            output: false,
            name: 'put_sort',
        ),
    ],
    normalizationContext: [
        'groups' => [AttributeDefinition::GROUP_LIST, AttributeDefinition::GROUP_READ],
    ],
    input: AttributeDefinitionInput::class,
    output: AttributeDefinitionOutput::class,
    paginationClientItemsPerPage: true,
    paginationMaximumItemsPerPage: 1000,
)]
#[ORM\Table]
#[ORM\Index(columns: ['searchable'], name: 'searchable_idx')]
#[ORM\Index(columns: ['field_type'], name: 'type_idx')]
#[ORM\UniqueConstraint(name: 'uniq_attr_def_ws_name', columns: ['workspace_id', 'name'])]
#[ORM\UniqueConstraint(name: 'uniq_attr_def_ws_key', columns: ['workspace_id', 'key'])]
#[ORM\UniqueConstraint(name: 'uniq_attr_def_ws_slug', columns: ['workspace_id', 'slug'])]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
#[ORM\Entity(repositoryClass: AttributeDefinitionRepository::class)]
#[SameWorkspaceConstraint(
    properties: [
        'workspace',
        'policy.workspace',
    ],
)]
#[UniqueEntity(
    fields: ['workspace', 'name'],
    errorPath: 'name',
)]
#[ApiFilter(BooleanFilter::class, properties: ['searchable', 'facetEnabled', 'translatable', 'multiple', 'enabled'])]
class AttributeDefinition extends AbstractUuidEntity implements \Stringable, ErrorDisableInterface, LoggableChangeSetInterface
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    use WorkspaceTrait;
    use ErrorDisableTrait;
    use TranslationsTrait;
    use AssetTypeTargetTrait;
    final public const int OBJECT_INDEX = 14;

    final public const string GROUP_READ = 'attrdef:r';
    final public const string GROUP_LIST = 'attrdef:i';

    private const string OPT_EDITABLE_IN_GUI = 'gui-edit';

    /**
     * Override trait for annotation.
     */
    #[ORM\ManyToOne(targetEntity: Workspace::class, inversedBy: 'attributeDefinitions')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    protected ?Workspace $workspace = null;

    #[ORM\ManyToOne(targetEntity: AttributePolicy::class, inversedBy: 'definitions')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    protected ?AttributePolicy $policy = null;

    /**
     * @var Attribute[]
     */
    #[ORM\OneToMany(mappedBy: 'definition', targetEntity: Attribute::class, cascade: ['remove'])]
    private ?DoctrineCollection $attributes = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: false)]
    // Keep this group for ApiPlatform "assertMatchesResourceItemJsonSchema" test
    #[Groups([self::GROUP_READ, Asset::GROUP_READ, Asset::GROUP_LIST])]
    #[Assert\NotNull]
    #[Assert\NotBlank]
    private ?string $name = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    #[Gedmo\Slug(fields: ['name'], updatable: false, style: 'lower', unique: false, separator: '')]
    private ?string $slug = null;

    /**
     * Apply this definition to files of this MIME type.
     * If null, applied to all files.
     */
    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $fileType = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: false)]
    private string $fieldType = TextAttributeType::NAME;

    #[ORM\ManyToOne(targetEntity: EntityList::class, inversedBy: 'definitions')]
    #[ORM\JoinColumn(nullable: true)]
    protected ?EntityList $entityList = null;

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

    #[ORM\Column(type: Types::BOOLEAN, nullable: false)]
    private bool $editable = true;

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

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $options = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: false)]
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

    public function getPolicy(): ?AttributePolicy
    {
        return $this->policy;
    }

    public function setPolicy(?AttributePolicy $policy): void
    {
        $this->policy = $policy;
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
        if (is_array($this->initialValues) && empty(array_filter($this->initialValues))) {
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

    public function getEntityList(): ?EntityList
    {
        return $this->entityList;
    }

    public function setEntityList(?EntityList $entityList): void
    {
        $this->entityList = $entityList;
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

    public function setEditable(bool $editable): void
    {
        $this->editable = $editable;
    }

    public function isEditable(): bool
    {
        return $this->editable;
    }

    public function setEditableInGui(bool $editable): void
    {
        $this->options[self::OPT_EDITABLE_IN_GUI] = $editable;
    }

    public function isEditableInGui(): bool
    {
        return $this->options[self::OPT_EDITABLE_IN_GUI] ?? true;
    }

    #[Assert\Callback]
    public function validateInitialValues(ExecutionContextInterface $context): void
    {
        if (empty($this->initialValues)) {
            return;
        }

        foreach ($this->initialValues as $locale => $value) {
            $data = json_decode($value, true);
            if (JSON_ERROR_NONE !== json_last_error()) {
                $context->buildViolation('The initial value for locale "%locale%" is not valid JSON.')
                    ->setParameter('%locale%', $locale)
                    ->atPath('initialValues')
                    ->addViolation();
                continue;
            }

            if (!is_array($data)) {
                $context->buildViolation('The initial value for locale "%locale%" must be an array.')
                    ->setParameter('%locale%', $locale)
                    ->atPath('initialValues')
                    ->addViolation();
                continue;
            }

            foreach ([
                'type',
                'value',
            ] as $key) {
                if (!isset($data[$key])) {
                    $context->buildViolation('The initial value for locale "%locale%" must contain a "%key%" key.')
                        ->setParameter('%locale%', $locale)
                        ->setParameter('%key%', $key)
                        ->atPath('initialValues')
                        ->addViolation();
                }
            }
        }
    }

    public function getOwnerId(): ?string
    {
        return $this->getWorkspace()?->getOwnerId();
    }
}
