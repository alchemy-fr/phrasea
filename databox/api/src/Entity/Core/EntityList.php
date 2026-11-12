<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\AclBundle\AclObjectInterface;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Alchemy\CoreBundle\Entity\Traits\UpdatedAtTrait;
use Alchemy\TrackBundle\LoggableChangeSetInterface;
use ApiPlatform\Doctrine\Orm\Filter\PartialSearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\SortFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use App\Api\Filter\ExactSearchFilter;
use App\Api\Model\Input\EntityHierarchyImportInput;
use App\Api\Model\Input\ExportEntitiesInput;
use App\Api\Model\Input\ImportEntitiesInput;
use App\Api\Processor\ClearEntitiesProcessor;
use App\Api\Processor\ExportEntitiesProcessor;
use App\Api\Processor\ExportEntityHierarchyProcessor;
use App\Api\Processor\ImportEntitiesProcessor;
use App\Api\Processor\ImportEntityHierarchyProcessor;
use App\Api\Provider\EntityListImportTaskProvider;
use App\Audit\AuditableInterface;
use App\Entity\Traits\OwnerIdTrait;
use App\Entity\Traits\WorkspaceTrait;
use App\Listener\OwnerPersistableInterface;
use App\Security\Voter\EntityListVoter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'entity-list',
    operations: [
        new Get(security: 'is_granted("READ", object)'),
        new Delete(security: 'is_granted("DELETE", object)'),
        new Patch(
            security: 'is_granted("EDIT", object)',
            denormalizationContext: [
                'groups' => [self::GROUP_WRITE],
                AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false,
            ],
        ),
        // Restricted to the readable workspaces by EntityListExtension
        new GetCollection(
            order: ['name' => 'ASC', 'id' => 'ASC'],
            security: 'is_granted("'.JwtUser::IS_AUTHENTICATED_FULLY.'")',
            parameters: [
                'workspace' => new QueryParameter(
                    filter: ExactSearchFilter::class,
                    property: 'workspace',
                ),
                'workspace[]' => new QueryParameter(property: 'workspace', openApi: false),
                'name' => new QueryParameter(
                    filter: new PartialSearchFilter(),
                    property: 'name',
                    schema: ['type' => 'string'],
                    castToArray: false,
                ),
                'order[name]' => new QueryParameter(filter: new SortFilter(), property: 'name', castToArray: false),
                'order[createdAt]' => new QueryParameter(filter: new SortFilter(), property: 'createdAt', castToArray: false),
            ],
        ),
        new Post(
            denormalizationContext: [
                'groups' => [self::GROUP_WRITE, self::GROUP_CREATE],
                AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false,
            ],
            securityPostDenormalize: 'is_granted("CREATE", object)'
        ),
        new Post(
            uriTemplate: '/entity-lists/{id}/import',
            security: 'is_granted("'.EntityListVoter::EDIT_VALUES.'", object)',
            input: ImportEntitiesInput::class,
            name: 'import_entities',
            processor: ImportEntitiesProcessor::class
        ),
        new Get(
            uriTemplate: '/entity-lists/{id}/import-tasks/{taskId}',
            uriVariables: [
                'id' => 'id',
                'taskId' => 'taskId',
            ],
            name: 'import_entities_task',
            provider: EntityListImportTaskProvider::class,
        ),
        new Post(
            uriTemplate: '/entity-lists/{id}/export',
            security: 'is_granted("'.EntityListVoter::EDIT_VALUES.'", object)',
            input: ExportEntitiesInput::class,
            name: 'export_entities',
            processor: ExportEntitiesProcessor::class
        ),
        new Post(
            uriTemplate: '/entity-lists/{id}/hierarchy-import',
            security: 'is_granted("EDIT", object)',
            input: EntityHierarchyImportInput::class,
            name: 'import_entity_hierarchy',
            processor: ImportEntityHierarchyProcessor::class
        ),
        new Post(
            uriTemplate: '/entity-lists/{id}/hierarchy-export',
            security: 'is_granted("EDIT", object)',
            input: false,
            name: 'export_entity_hierarchy',
            processor: ExportEntityHierarchyProcessor::class
        ),
        new Post(
            uriTemplate: '/entity-lists/{id}/clear',
            security: 'is_granted("'.EntityListVoter::EDIT_VALUES.'", object)',
            input: false,
            name: 'clear_entities',
            processor: ClearEntitiesProcessor::class
        ),
    ],
    normalizationContext: [
        'groups' => [
            self::GROUP_LIST,
        ],
    ],
)]

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_ws_type', columns: ['workspace_id', 'name'])]
#[UniqueEntity(
    fields: ['workspace', 'name'],
    message: 'This entity type already exists in the workspace.'
)]
class EntityList extends AbstractUuidEntity implements AuditableInterface, LoggableChangeSetInterface, OwnerPersistableInterface, AclObjectInterface, \Stringable
{
    use CreatedAtTrait;
    use OwnerIdTrait;
    use UpdatedAtTrait;
    use WorkspaceTrait;
    final public const int OBJECT_INDEX = 15;
    final public const string OBJECT_TYPE = 'entity_list';
    public const int TYPE_LENGTH = 100;

    private const string ALLOW_NEW_VALUES = 'nv';
    private const string APPROVE_NEW_VALUES = 'av';
    private const string WITH_EMOJIS = 'we';
    private const string WITH_COLORS = 'wc';
    private const string WITH_IMAGES = 'wi';
    private const string WITH_TRANSLATIONS = 'wt';
    private const string WITH_SYNONYMS = 'ws';
    private const string IS_HIERARCHY = 'h';

    final public const string GROUP_READ = 'entity-list:r';
    final public const string GROUP_LIST = 'entity-list:i';
    /**
     * The workspace is only set at creation, and the owner is never written through the API.
     */
    final public const string GROUP_WRITE = 'entity-list:w';
    final public const string GROUP_CREATE = 'entity-list:c';
    private const array COMMON_GROUPS = [
        self::GROUP_READ,
        self::GROUP_LIST,
        AttributeDefinition::GROUP_LIST,
        self::GROUP_WRITE,
    ];

    #[ORM\Column(type: Types::STRING, length: 36, nullable: true)]
    // Nullable because lists may be created without a user (fixtures, workspace templates)
    protected ?string $ownerId = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: false)]
    #[Groups([self::GROUP_LIST, self::GROUP_READ, self::GROUP_WRITE])]
    #[Assert\NotBlank]
    #[Assert\Length(max: self::TYPE_LENGTH)]
    private ?string $name = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $options = null;

    #[ORM\OneToMany(mappedBy: 'list', targetEntity: AttributeEntity::class, cascade: ['remove'])]
    private ?Collection $entities = null;

    #[ORM\OneToMany(mappedBy: 'entityList', targetEntity: AttributeDefinition::class, cascade: ['remove'])]
    private ?Collection $definitions = null;

    public function __construct()
    {
        $this->entities = new ArrayCollection();
        $this->definitions = new ArrayCollection();
        parent::__construct();
    }

    /**
     * EntityListVoter grants nothing from this ownership: only the workspace rights and the list ACEs count.
     */
    public function getAclOwnerId(): string
    {
        return $this->ownerId ?? '';
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function __toString(): string
    {
        return $this->name ?? $this->getId() ?? '';
    }

    #[Groups([self::GROUP_READ, self::GROUP_LIST, self::GROUP_CREATE])]
    public function getWorkspace(): ?Workspace
    {
        return $this->workspace;
    }

    #[Groups([self::GROUP_READ, self::GROUP_LIST])]
    public function getDefinitions(): ?Collection
    {
        return $this->definitions;
    }

    #[Groups(self::COMMON_GROUPS)]
    public function isAllowNewValues(): bool
    {
        return $this->options[self::ALLOW_NEW_VALUES] ?? false;
    }

    public function setAllowNewValues(bool $allowNewValues): void
    {
        $this->options[self::ALLOW_NEW_VALUES] = $allowNewValues;
    }

    #[Groups(self::COMMON_GROUPS)]
    public function isApproveNewValues(): bool
    {
        return $this->options[self::APPROVE_NEW_VALUES] ?? false;
    }

    public function setApproveNewValues(bool $approveNewValues): void
    {
        $this->options[self::APPROVE_NEW_VALUES] = $approveNewValues;
    }

    #[Groups(self::COMMON_GROUPS)]
    public function isWithEmojis(): bool
    {
        return $this->options[self::WITH_EMOJIS] ?? false;
    }

    public function setWithEmojis(bool $withEmojis): void
    {
        $this->options[self::WITH_EMOJIS] = $withEmojis;
    }

    #[Groups(self::COMMON_GROUPS)]
    public function isWithColors(): bool
    {
        return $this->options[self::WITH_COLORS] ?? false;
    }

    public function setWithColors(bool $withColor): void
    {
        $this->options[self::WITH_COLORS] = $withColor;
    }

    #[Groups(self::COMMON_GROUPS)]
    public function isWithImages(): bool
    {
        return $this->options[self::WITH_IMAGES] ?? false;
    }

    public function setWithImages(bool $withImages): void
    {
        $this->options[self::WITH_IMAGES] = $withImages;
    }

    #[Groups(self::COMMON_GROUPS)]
    public function isWithTranslations(): bool
    {
        return $this->options[self::WITH_TRANSLATIONS] ?? false;
    }

    public function setWithTranslations(bool $withTranslations): void
    {
        $this->options[self::WITH_TRANSLATIONS] = $withTranslations;
    }

    #[Groups(self::COMMON_GROUPS)]
    public function isWithSynonyms(): bool
    {
        return $this->options[self::WITH_SYNONYMS] ?? false;
    }

    public function setWithSynonyms(bool $withSynonyms): void
    {
        $this->options[self::WITH_SYNONYMS] = $withSynonyms;
    }

    /**
     * Whether the entities may form a hierarchy. Turned off, the hierarchy is flattened (see EntityListFlattener).
     */
    public function isHierarchy(): bool
    {
        return self::isHierarchyOption($this->options);
    }

    /**
     * The flag in a state of the options (e.g. from a Doctrine changeset).
     */
    public static function isHierarchyOption(?array $options): bool
    {
        return $options[self::IS_HIERARCHY] ?? false;
    }

    /**
     * Exposed as "isHierarchy", the name of its setter.
     */
    #[Groups(self::COMMON_GROUPS)]
    public function getIsHierarchy(): bool
    {
        return $this->isHierarchy();
    }

    public function setIsHierarchy(bool $isHierarchy): void
    {
        $this->options[self::IS_HIERARCHY] = $isHierarchy;
    }

    public function getEntities(): ?Collection
    {
        return $this->entities;
    }
}
