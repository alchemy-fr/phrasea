<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Alchemy\CoreBundle\Entity\Traits\UpdatedAtTrait;
use Alchemy\CoreBundle\Util\LocaleUtil;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Api\Model\Input\MergeAttributeEntitiesInput;
use App\Api\Model\Output\ResolveEntitiesOutput;
use App\Api\Processor\AddAttributeEntityProcessor;
use App\Api\Processor\MergeAttributeEntitiesProcessor;
use App\Api\Provider\AttributeEntityCollectionProvider;
use App\Entity\Traits\WorkspaceTrait;
use App\Repository\Core\AttributeEntityRepository;
use App\Validator\SameWorkspaceConstraint;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\UniqueConstraint;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'attribute-entity',
    operations: [
        new Get(),
        new Delete(security: 'is_granted("DELETE", object)'),
        new Put(security: 'is_granted("EDIT", object)'),
        new Patch(security: 'is_granted("EDIT", object)'),
        new GetCollection(
            normalizationContext: [
                AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true,
                'groups' => [
                    self::GROUP_LIST,
                ],
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
    ],
    normalizationContext: [
        AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true,
        'groups' => [
            self::GROUP_READ,
            self::GROUP_LIST,
        ],
    ],
    provider: AttributeEntityCollectionProvider::class,
)]

#[ORM\Entity(repositoryClass: AttributeEntityRepository::class)]
#[ApiFilter(filterClass: SearchFilter::class, properties: [
    'workspace' => 'exact',
    'list' => 'exact',
    'value' => 'ipartial',
])]
#[ApiFilter(filterClass: OrderFilter::class, properties: [
    'value',
    'createdAt',
    'position',
])]
#[ORM\Index(columns: ['list_id'], name: 'entity_list_idx')]
#[ORM\Index(columns: ['parent_id'], name: 'idx_attribute_entity_parent')]
#[SameWorkspaceConstraint(
    properties: ['workspace', 'list.workspace'],
)]
#[UniqueConstraint(name: 'list_value_parent_uniq', fields: ['list', 'parent', 'value'], options: ['where' => 'parent_id IS NOT NULL'])]
#[UniqueConstraint(name: 'list_value_root_uniq', fields: ['list', 'value'], options: ['where' => 'parent_id IS NULL'])]
#[UniqueConstraint(name: 'list_external_id_uniq', fields: ['list', 'externalId'])]
#[UniqueEntity(fields: ['list', 'value', 'parent'], message: 'This value already exists in the branch', errorPath: 'value')]
#[UniqueEntity(fields: ['list', 'externalId'], message: 'This external ID already exists in the list', errorPath: 'externalId')]
class AttributeEntity extends AbstractUuidEntity implements \Stringable
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    use WorkspaceTrait;

    final public const string GROUP_READ = 'attr-ent:r';
    final public const string GROUP_LIST = 'attr-ent:i';

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

    #[ORM\Column(type: Types::INTEGER, nullable: false)]
    private int $position = 0;

    #[ORM\Column(type: Types::SMALLINT, nullable: false)]
    #[Groups([
        self::GROUP_LIST,
    ])]
    private int $status = self::STATUS_APPROVED;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $data = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups([
        self::GROUP_LIST,
        ResolveEntitiesOutput::GROUP_READ,
        Asset::GROUP_LIST,
    ])]
    private ?array $translations = null;

    #[ORM\Column(type: Types::STRING, length: 36, nullable: true)]
    protected ?string $creatorId = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups([self::GROUP_READ, self::GROUP_LIST])]
    #[MaxDepth(1)]
    private ?self $parent = null;

    /**
     * @var Collection<int, self>
     */
    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class)]
    #[Groups([self::GROUP_READ, self::GROUP_LIST])]
    #[MaxDepth(1)]
    private Collection $children;

    #[ORM\OneToOne(targetEntity: EntityType::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\JoinColumn(nullable: true, unique: true)]
    private ?EntityType $entityTypeEntity = null;

    private ?string $entityType = null;

    public function __construct()
    {
        parent::__construct();
        $this->children = new ArrayCollection();
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): void
    {
        if ($this->parent === $parent) {
            return;
        }

        $this->parent?->children->removeElement($this);
        $this->parent = $parent;
        $parent?->children->add($this);
    }

    /**
     * @return Collection<int, self>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function addChild(self $child): void
    {
        $child->setParent($this);
    }

    public function removeChild(self $child): void
    {
        if ($child->getParent() === $this) {
            $child->setParent(null);
        }
    }

    #[Groups([self::GROUP_READ, self::GROUP_LIST])]
    #[Assert\Length(max: 100)]
    public function getEntityType(): ?string
    {
        return $this->entityType ?? $this->entityTypeEntity?->getName();
    }

    public function setEntityType(?string $entityType): void
    {
        if (null === $entityType) {
            $this->entityTypeEntity = null;
            $this->entityType = null;

            return;
        }

        $this->setEntityTypeEntity((new EntityType())->setName($entityType));
    }

    public function setEntityTypeEntity(EntityType $entityType): void
    {
        $this->entityTypeEntity = $entityType;
        $this->entityType = $entityType->getName();
    }

    public function getEntityTypeEntity(): ?EntityType
    {
        return $this->entityTypeEntity;
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

    public function __toString(): string
    {
        return $this->value ?? $this->getId() ?? '';
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(?string $externalId): void
    {
        $this->externalId = $externalId;
    }
}
