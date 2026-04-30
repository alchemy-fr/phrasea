<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Alchemy\CoreBundle\Entity\Traits\UpdatedAtTrait;
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
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\UniqueConstraint;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
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
                'groups' => [
                    self::GROUP_LIST,
                ],
            ],
        ),
        new Post(
            securityPostDenormalize: 'is_granted("CREATE", object)',
            processor: AddAttributeEntityProcessor::class,
        ),
        new Post(
            uriTemplate: '/attribute-entities/{id}/merge',
            input: MergeAttributeEntitiesInput::class,
            name: 'entities_merge',
            processor: MergeAttributeEntitiesProcessor::class,
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
#[SameWorkspaceConstraint(
    properties: ['workspace', 'list.workspace'],
)]
#[UniqueConstraint(name: 'list_value_uniq', fields: ['list', 'value'])]
#[UniqueEntity(fields: ['list', 'value'], message: 'This value already exists in the list', errorPath: 'value')]
class AttributeEntity extends AbstractUuidEntity
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

    public function __toString(): string
    {
        return $this->value ?? $this->getId() ?? '';
    }
}
