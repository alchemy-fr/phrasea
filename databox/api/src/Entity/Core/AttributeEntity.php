<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Alchemy\CoreBundle\Entity\Traits\UpdatedAtTrait;
use Alchemy\CoreBundle\Util\LocaleUtil;
use ApiPlatform\Doctrine\Orm\Filter\PartialSearchFilter as OrmPartialSearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\SortFilter as OrmSortFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\QueryParameter;
use App\Api\Filter\ExactSearchFilter;
use App\Api\Filter\PartialSearchFilter;
use App\Api\Filter\SortFilter;
use App\Api\Model\Input\MergeAttributeEntitiesInput;
use App\Api\Model\Output\EntityHierarchyImpactOutput;
use App\Api\Model\Output\ResolveEntitiesOutput;
use App\Api\Processor\AddAttributeEntityProcessor;
use App\Api\Processor\AttributeEntityHierarchyProcessor;
use App\Api\Processor\DeleteAttributeEntityImageProcessor;
use App\Api\Processor\DeleteAttributeEntityProcessor;
use App\Api\Processor\MergeAttributeEntitiesProcessor;
use App\Api\Processor\UploadAttributeEntityImageProcessor;
use App\Api\Provider\AttributeEntityCollectionProvider;
use App\Api\Provider\EntityHierarchyImpactProvider;
use App\Elasticsearch\Filter\ElasticsearchFilterInterface;
use App\Elasticsearch\Filter\SuggestQueryFilter;
use App\Entity\Traits\WorkspaceTrait;
use App\Repository\Core\AttributeEntityRepository;
use App\Validator\SameWorkspaceConstraint;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\UniqueConstraint;
use Ramsey\Uuid\UuidInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ApiResource(
    shortName: 'attribute-entity',
    operations: [
        new Get(security: 'is_granted("READ", object)'),
        new Delete(
            security: 'is_granted("DELETE", object)',
            processor: DeleteAttributeEntityProcessor::class,
        ),
        // Moving a value to another list takes the edition of both lists
        new Patch(
            security: 'is_granted("EDIT", object)',
            securityPostDenormalize: self::LIST_CHANGE_SECURITY,
            processor: AttributeEntityHierarchyProcessor::class,
        ),
        // EDIT on the entity, checked by the provider
        new Get(
            uriTemplate: '/attribute-entities/{id}/hierarchy-impact',
            output: EntityHierarchyImpactOutput::class,
            name: 'hierarchy_impact',
            provider: EntityHierarchyImpactProvider::class,
        ),
        new GetCollection(
            normalizationContext: [
                'groups' => [
                    self::GROUP_LIST,
                ],
            ],
            parameters: [
                'workspace' => new QueryParameter(
                    filter: ExactSearchFilter::class,
                    property: 'workspace',
                    extraProperties: [ElasticsearchFilterInterface::ES_FIELD => 'workspaceId'],
                ),
                'workspace[]' => new QueryParameter(property: 'workspace', openApi: false),
                'list' => new QueryParameter(
                    filter: ExactSearchFilter::class,
                    property: 'list',
                    extraProperties: [ElasticsearchFilterInterface::ES_FIELD => 'listId'],
                ),
                'list[]' => new QueryParameter(property: 'list', openApi: false),
                'value' => new QueryParameter(
                    filter: new PartialSearchFilter(),
                    property: 'value',
                    schema: ['type' => 'string'],
                    extraProperties: [ElasticsearchFilterInterface::ES_FIELD => 'value.raw'],
                    castToArray: false,
                ),
                'status' => new QueryParameter(
                    filter: ExactSearchFilter::class,
                    property: 'status',
                    schema: ['type' => 'integer'],
                    description: 'Approved (0), pending (1) or rejected (2) values only, one value or a list (status[]=0&status[]=1); ignored on a "query" search',
                    castToArray: false,
                ),
                'status[]' => new QueryParameter(property: 'status', openApi: false),
                'note' => new QueryParameter(
                    filter: new OrmPartialSearchFilter(),
                    property: 'note',
                    schema: ['type' => 'string'],
                    description: 'Contains match on the note (ignored on a "query" search)',
                    castToArray: false,
                ),
                'usage' => new QueryParameter(
                    schema: ['type' => 'boolean'],
                    description: 'Also counts the attribute values referencing each entity ("usage")',
                    castToArray: false,
                ),
                'query' => new QueryParameter(
                    filter: new SuggestQueryFilter(),
                    schema: ['type' => 'string'],
                    description: 'Search-as-you-type on the value (switches the search to Elasticsearch)',
                    extraProperties: [ElasticsearchFilterInterface::ES_FIELD => 'value'],
                    castToArray: false,
                ),
                'order[value]' => new QueryParameter(
                    filter: new SortFilter(),
                    property: 'value',
                    extraProperties: [ElasticsearchFilterInterface::ES_FIELD => 'value.raw'],
                    castToArray: false,
                ),
                // createdAt, updatedAt, status, note and position are not indexed: ORM only (ignored on a "query" search)
                'order[createdAt]' => new QueryParameter(filter: new OrmSortFilter(), property: 'createdAt', castToArray: false),
                'order[updatedAt]' => new QueryParameter(filter: new OrmSortFilter(), property: 'updatedAt', castToArray: false),
                'order[status]' => new QueryParameter(filter: new OrmSortFilter(), property: 'status', castToArray: false),
                'order[note]' => new QueryParameter(filter: new OrmSortFilter(), property: 'note', castToArray: false),
                'order[position]' => new QueryParameter(filter: new OrmSortFilter(), property: 'position', castToArray: false),
                'parent' => new QueryParameter(
                    schema: ['type' => 'string'],
                    description: 'Direct children of this entity (ID), ordered by position',
                    castToArray: false,
                ),
                'depth' => new QueryParameter(
                    schema: ['type' => 'integer'],
                    description: 'Entities at this depth of the list hierarchy (0 is the root entity)',
                    castToArray: false,
                ),
                'branch' => new QueryParameter(
                    schema: ['type' => 'string'],
                    description: 'Descendants of this entity (ID)',
                    castToArray: false,
                ),
                'unclassified' => new QueryParameter(
                    schema: ['type' => 'boolean'],
                    description: 'Entities placed in no hierarchy (true) or placed ones (false)',
                    castToArray: false,
                ),
                'placeholder' => new QueryParameter(
                    schema: ['type' => 'boolean'],
                    description: 'Hierarchy placeholders (empty levels) only (true) or excluded (false)',
                    castToArray: false,
                ),
                'definition' => new QueryParameter(
                    schema: ['type' => 'string'],
                    description: 'Entities accepted by this attribute definition (ID): its hierarchy depth and branch when it is bound to one',
                    castToArray: false,
                ),
                'limit' => new QueryParameter(
                    schema: ['type' => 'integer'],
                    description: 'Page size (max 50 on a "query" search)',
                    castToArray: false,
                ),
                'page' => new QueryParameter(
                    schema: ['type' => 'integer'],
                    castToArray: false,
                ),
            ],
        ),
        new Post(
            securityPostDenormalize: 'is_granted("CREATE", object)',
            processor: AddAttributeEntityProcessor::class,
        ),
        new Put(
            uriTemplate: '/attribute-entities/{id}/merge',
            input: MergeAttributeEntitiesInput::class,
            name: 'entities_merge',
            processor: MergeAttributeEntitiesProcessor::class,
        ),
        new Post(
            uriTemplate: '/attribute-entities/{id}/image',
            security: 'is_granted("EDIT", object)',
            input: false,
            throwOnNotFound: true,
            processor: UploadAttributeEntityImageProcessor::class,
            name: 'upload_image',
        ),
        new Delete(
            uriTemplate: '/attribute-entities/{id}/image',
            security: 'is_granted("EDIT", object)',
            processor: DeleteAttributeEntityImageProcessor::class,
            name: 'delete_image',
        ),
    ],
    normalizationContext: [
        'groups' => [
            self::GROUP_READ,
            self::GROUP_LIST,
        ],
    ],
    provider: AttributeEntityCollectionProvider::class,
)]

#[ORM\Entity(repositoryClass: AttributeEntityRepository::class)]
#[ORM\Index(columns: ['list_id'], name: 'entity_list_idx')]
#[ORM\Index(columns: ['list_id', 'parent_id', 'position'], name: 'entity_parent_idx')]
#[ORM\Index(columns: ['list_id', 'hierarchy_depth'], name: 'entity_depth_idx')]
#[SameWorkspaceConstraint(
    properties: ['workspace', 'list.workspace'],
)]
#[UniqueConstraint(name: 'list_external_id_uniq', fields: ['list', 'externalId'])]
#[UniqueEntity(fields: ['list', 'externalId'], message: 'This external ID already exists in the list', errorPath: 'externalId')]
class AttributeEntity extends AbstractUuidEntity implements \Stringable
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    use WorkspaceTrait;

    final public const string GROUP_READ = 'attr-ent:r';
    final public const string GROUP_LIST = 'attr-ent:i';
    private const string LIST_CHANGE_SECURITY = 'object.getListId() == previous_object.getListId() or (is_granted("EDIT", previous_object.getList()) and is_granted("EDIT", object.getList()))';

    final public const int STATUS_APPROVED = 0;
    final public const int STATUS_PENDING = 1;
    final public const int STATUS_REJECTED = 2;

    private const string DATA_EMOJI = 'e';
    private const string DATA_COLOR = 'c';

    public const array STATUS_CHOICES = [
        'Approved' => self::STATUS_APPROVED,
        'Pending' => self::STATUS_PENDING,
        'Rejected' => self::STATUS_REJECTED,
    ];

    #[ORM\ManyToOne(targetEntity: EntityList::class, inversedBy: 'entities')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?EntityList $list = null;

    #[ORM\Column(type: Types::TEXT, nullable: false)]
    #[Groups([
        self::GROUP_LIST,
        ResolveEntitiesOutput::GROUP_READ,
        Asset::GROUP_LIST,
    ])]
    #[Assert\NotBlank]
    private ?string $value = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups([
        self::GROUP_LIST,
    ])]
    #[Assert\Type('array')]
    #[Assert\All([
        new Assert\Type('array'),
        new Assert\All([
            new Assert\Type('string'),
            new Assert\NotBlank(),
            new Assert\Length(max: 100),
        ]),
    ])]
    private ?array $synonyms = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $externalId = null;

    /**
     * Free text telling apart the values sharing the same name (homonyms).
     */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Groups([
        self::GROUP_LIST,
        ResolveEntitiesOutput::GROUP_READ,
        Asset::GROUP_LIST,
    ])]
    #[Assert\Length(max: 255)]
    private ?string $note = null;

    /**
     * Number of attribute values referencing the entity, computed on demand by the collection
     * provider ("usage" parameter): {attributes: int, humanAttributes: int}.
     *
     * @var array{attributes: int, humanAttributes: int}|null
     */
    #[Groups([self::GROUP_LIST])]
    #[ApiProperty(writable: false)]
    private ?array $usage = null;

    /**
     * Order among the siblings of the hierarchy.
     */
    #[ORM\Column(type: Types::INTEGER, nullable: false)]
    #[Groups([self::GROUP_LIST])]
    private int $position = 0;

    /**
     * Parent in the list hierarchy; null for the root entity and the unclassified ones.
     * Moved by AttributeEntityHierarchyProcessor (EntityHierarchyManager).
     */
    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?self $parent = null;

    /**
     * @var Collection<int, self>
     */
    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $children;

    /**
     * Absolute depth in the list hierarchy (0 = root entity), null when the entity is not classified.
     * Maintained by EntityHierarchyManager, never written from the input.
     */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Groups([self::GROUP_LIST])]
    #[ApiProperty(writable: false)]
    private ?int $hierarchyDepth = null;

    /**
     * Fills an empty level of the hierarchy (e.g. no district between a city and its streets):
     * never proposed nor written in an attribute.
     */
    #[ORM\Column(type: Types::BOOLEAN, nullable: false, options: ['default' => false])]
    #[Groups([self::GROUP_LIST])]
    private bool $placeholder = false;

    /**
     * Number of direct children, computed on read by the collection provider.
     */
    #[Groups([self::GROUP_LIST])]
    #[ApiProperty(writable: false)]
    private ?int $childCount = null;

    /**
     * Only the list moderators (its editors) change the status: a change by anyone else is reverted.
     */
    #[ORM\Column(type: Types::SMALLINT, nullable: false)]
    #[Groups([
        self::GROUP_LIST,
    ])]
    #[ApiProperty(securityPostDenormalize: 'is_granted("EDIT", object.getList())')]
    private int $status = self::STATUS_APPROVED;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $data = null;

    /**
     * Exposed as "image" and "thumbnail" URLs by AttributeEntityNormalizer.
     */
    #[ORM\OneToOne(targetEntity: ImageSet::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ImageSet $image = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups([
        self::GROUP_LIST,
        ResolveEntitiesOutput::GROUP_READ,
        Asset::GROUP_LIST,
    ])]
    private ?array $translations = null;

    /**
     * Set on creation (AddAttributeEntityProcessor), never from the input.
     */
    #[ORM\Column(type: Types::STRING, length: 36, nullable: true)]
    #[ApiProperty(writable: false)]
    protected ?string $creatorId = null;

    public function __construct(string|UuidInterface|null $id = null)
    {
        parent::__construct($id);
        $this->children = new ArrayCollection();
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): void
    {
        $this->value = $value;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getTranslations(): ?array
    {
        return $this->translations;
    }

    public function setTranslations(?array $translations): void
    {
        if (null !== $translations) {
            foreach ($translations as $locale => $v) {
                if (is_numeric($locale) || empty($v)) {
                    unset($translations[$locale]);
                } elseif (LocaleUtil::normalizeLocale($locale) !== $locale) {
                    $translations[LocaleUtil::normalizeLocale($locale)] = $v;
                    unset($translations[$locale]);
                }
            }
        }

        $this->translations = $translations;
    }

    public function getList(): ?EntityList
    {
        return $this->list;
    }

    /**
     * Used by ES.
     */
    public function getListId(): ?string
    {
        return $this->list?->getId();
    }

    public function setList(?EntityList $list): void
    {
        if (null !== $list && null === $this->workspace) {
            $this->setWorkspace($list->getWorkspace());
        }

        $this->list = $list;
    }

    public function getSynonyms(): ?array
    {
        if (empty($this->synonyms)) {
            return null;
        }

        return $this->synonyms;
    }

    public function setSynonyms(?array $synonyms): void
    {
        if (null !== $synonyms) {
            foreach ($synonyms as $locale => $lSynonyms) {
                if (is_numeric($locale) || empty($lSynonyms)) {
                    unset($synonyms[$locale]);
                }
            }
        }

        $this->synonyms = $synonyms;
    }

    public function getSynonymsOfLocale(string $locale): ?array
    {
        if (null === $this->synonyms) {
            return null;
        }

        return $this->synonyms[$locale] ?? null;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function setStatus(int $status): void
    {
        $this->status = $status;
    }

    public function getCreatorId(): ?string
    {
        return $this->creatorId;
    }

    public function setCreatorId(?string $creatorId): void
    {
        $this->creatorId = $creatorId;
    }

    public function isApproved(): bool
    {
        return self::STATUS_APPROVED === $this->status;
    }

    #[Groups([self::GROUP_LIST])]
    #[Assert\Length(min: 1, max: 1, countUnit: Assert\Length::COUNT_GRAPHEMES)]
    public function getEmoji(): ?string
    {
        return $this->data[self::DATA_EMOJI] ?? null;
    }

    public function setEmoji(?string $emoji): void
    {
        if (null !== $emoji) {
            $this->data[self::DATA_EMOJI] = $emoji;
        } else {
            unset($this->data[self::DATA_EMOJI]);
        }
    }

    #[Groups([self::GROUP_LIST])]
    #[Assert\CssColor]
    public function getColor(): ?string
    {
        return $this->data[self::DATA_COLOR] ?? null;
    }

    public function setColor(?string $color): void
    {
        if (null !== $color) {
            $this->data[self::DATA_COLOR] = $color;
        } else {
            unset($this->data[self::DATA_COLOR]);
        }
    }

    public function getImage(): ?ImageSet
    {
        return $this->image;
    }

    public function setImage(?ImageSet $image): void
    {
        $this->image = $image;
    }

    public function __toString(): string
    {
        return $this->value ?? $this->getId() ?? '';
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): void
    {
        $note = null !== $note ? trim($note) : null;
        $this->note = '' !== $note ? $note : null;
    }

    /**
     * @return array{attributes: int, humanAttributes: int}|null
     */
    public function getUsage(): ?array
    {
        return $this->usage;
    }

    /**
     * @param array{attributes: int, humanAttributes: int}|null $usage
     */
    public function setUsage(?array $usage): void
    {
        $this->usage = $usage;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(?string $externalId): void
    {
        $this->externalId = $externalId;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): void
    {
        $this->parent = $parent;
    }

    #[Groups([self::GROUP_LIST])]
    public function getParentId(): ?string
    {
        return $this->parent?->getId();
    }

    /**
     * From the parent up to the root. Used by ES.
     *
     * @return string[]
     */
    public function getAncestorIds(): array
    {
        $ids = [];
        $ancestor = $this->parent;
        while (null !== $ancestor && \count($ids) < 100) {
            $ids[] = $ancestor->getId();
            $ancestor = $ancestor->getParent();
        }

        return $ids;
    }

    /**
     * @return Collection<int, self>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function getHierarchyDepth(): ?int
    {
        return $this->hierarchyDepth;
    }

    public function setHierarchyDepth(?int $hierarchyDepth): void
    {
        $this->hierarchyDepth = $hierarchyDepth;
    }

    /**
     * Whether the entity is placed in the hierarchy of its list (the root included).
     */
    public function isClassified(): bool
    {
        return null !== $this->hierarchyDepth;
    }

    public function isPlaceholder(): bool
    {
        return $this->placeholder;
    }

    public function setPlaceholder(bool $placeholder): void
    {
        $this->placeholder = $placeholder;
    }

    /**
     * Ancestors from the top (root and placeholders left out): tells homonyms apart, e.g. "16e / Paris / Île-de-France".
     * The collection provider preloads the ancestors of a page.
     *
     * @return list<array{id: string, value: ?string, translations: ?array}>
     */
    #[Groups([self::GROUP_LIST, ResolveEntitiesOutput::GROUP_READ])]
    public function getPath(): array
    {
        $path = [];
        $ancestor = $this->parent;
        while (null !== $ancestor && \count($path) < 100) {
            if (!$ancestor->isPlaceholder() && 0 !== $ancestor->getHierarchyDepth()) {
                array_unshift($path, [
                    'id' => $ancestor->getId(),
                    'value' => $ancestor->getValue(),
                    'translations' => $ancestor->getTranslations(),
                ]);
            }
            $ancestor = $ancestor->getParent();
        }

        return $path;
    }

    public function getChildCount(): ?int
    {
        return $this->childCount;
    }

    public function setChildCount(?int $childCount): void
    {
        $this->childCount = $childCount;
    }

    #[Assert\Callback]
    public function validateHierarchy(ExecutionContextInterface $context): void
    {
        if (null === $this->parent) {
            return;
        }

        if ($this->parent->getListId() !== $this->getListId()) {
            $context->buildViolation('The parent must belong to the same list')->atPath('parent')->addViolation();

            return;
        }
        if ($this->list?->getRootEntity() === $this) {
            $context->buildViolation('The root entity cannot have a parent')->atPath('parent')->addViolation();

            return;
        }
        if (!$this->parent->isClassified()) {
            $context->buildViolation('The parent is not placed in the hierarchy')->atPath('parent')->addViolation();

            return;
        }

        $ancestor = $this->parent;
        while (null !== $ancestor) {
            if ($ancestor === $this) {
                $context->buildViolation('An entity cannot be its own ancestor')->atPath('parent')->addViolation();

                return;
            }
            $ancestor = $ancestor->getParent();
        }
    }
}
