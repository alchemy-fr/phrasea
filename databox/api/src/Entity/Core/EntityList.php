<?php

declare(strict_types=1);

namespace App\Entity\Core;

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
use App\Entity\Traits\OwnerIdTrait;
use App\Entity\Traits\WorkspaceTrait;
use App\Listener\OwnerPersistableInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ApiResource(
    shortName: 'entity-list',
    operations: [
        new Get(security: 'is_granted("READ", object)'),
        new Delete(security: 'is_granted("DELETE", object)'),
        new Patch(security: 'is_granted("EDIT", object)'),
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
            securityPostDenormalize: 'is_granted("CREATE", object)'
        ),
        new Post(
            uriTemplate: '/entity-lists/{id}/import',
            security: 'is_granted("EDIT", object)',
            input: ImportEntitiesInput::class,
            name: 'import_entities',
            processor: ImportEntitiesProcessor::class
        ),
        new Post(
            uriTemplate: '/entity-lists/{id}/export',
            security: 'is_granted("EDIT", object)',
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
            security: 'is_granted("EDIT", object)',
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
class EntityList extends AbstractUuidEntity implements LoggableChangeSetInterface, OwnerPersistableInterface, \Stringable
{
    use CreatedAtTrait;
    use OwnerIdTrait;
    use UpdatedAtTrait;
    use WorkspaceTrait;
    final public const int OBJECT_INDEX = 15;
    public const int TYPE_LENGTH = 100;

    private const string ALLOW_NEW_VALUES = 'nv';
    private const string APPROVE_NEW_VALUES = 'av';
    private const string WITH_EMOJIS = 'we';
    private const string WITH_COLORS = 'wc';
    private const string WITH_IMAGES = 'wi';
    private const string WITH_TRANSLATIONS = 'wt';
    private const string WITH_SYNONYMS = 'ws';

    final public const string GROUP_READ = 'entity-list:r';
    final public const string GROUP_LIST = 'entity-list:i';
    private const array COMMON_GROUPS = [
        self::GROUP_READ,
        self::GROUP_LIST,
        AttributeDefinition::GROUP_LIST,
    ];

    #[ORM\Column(type: Types::STRING, length: 36, nullable: true)]
    // Nullable because lists may be created without a user (fixtures, workspace templates)
    protected ?string $ownerId = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: false)]
    #[Groups([self::GROUP_LIST, self::GROUP_READ])]
    #[Assert\NotBlank]
    #[Assert\Length(max: self::TYPE_LENGTH)]
    private ?string $name = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $options = null;

    #[ORM\OneToMany(mappedBy: 'list', targetEntity: AttributeEntity::class, cascade: ['remove'])]
    private ?Collection $entities = null;

    #[ORM\OneToMany(mappedBy: 'entityList', targetEntity: AttributeDefinition::class, cascade: ['remove'])]
    private ?Collection $definitions = null;

    /**
     * Top of the list hierarchy (depth 0); null while the list is flat.
     */
    #[ORM\OneToOne(targetEntity: AttributeEntity::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?AttributeEntity $rootEntity = null;

    /**
     * Previous root, demoted on validation (see validateRootEntity).
     */
    private ?AttributeEntity $previousRootEntity = null;

    public function __construct()
    {
        $this->entities = new ArrayCollection();
        $this->definitions = new ArrayCollection();
        parent::__construct();
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

    #[Groups([self::GROUP_READ, self::GROUP_LIST])]
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

    public function getEntities(): ?Collection
    {
        return $this->entities;
    }

    #[Groups([self::GROUP_READ, self::GROUP_LIST])]
    public function getRootEntity(): ?AttributeEntity
    {
        return $this->rootEntity;
    }

    #[Groups([self::GROUP_READ, self::GROUP_LIST])]
    public function getRootEntityId(): ?string
    {
        return $this->rootEntity?->getId();
    }

    public function setRootEntity(?AttributeEntity $rootEntity): void
    {
        if ($rootEntity === $this->rootEntity) {
            return;
        }

        $this->previousRootEntity ??= $this->rootEntity;
        $this->previousRootEntity?->setHierarchyDepth(null);
        if (null !== $rootEntity && $rootEntity->getList() === $this && null === $rootEntity->getParent() && $rootEntity->getChildren()->isEmpty()) {
            $rootEntity->setHierarchyDepth(0);
        }
        $this->rootEntity = $rootEntity;
    }

    public function isHierarchical(): bool
    {
        return null !== $this->rootEntity;
    }

    #[Assert\Callback]
    public function validateRootEntity(ExecutionContextInterface $context): void
    {
        if (null !== $this->previousRootEntity && !$this->previousRootEntity->getChildren()->isEmpty()) {
            $context->buildViolation('The current root entity has children: move or remove them first')->atPath('rootEntity')->addViolation();
        }

        $root = $this->rootEntity;
        if (null === $root) {
            return;
        }
        if ($root->getList() !== $this) {
            $context->buildViolation('The root entity must belong to the list')->atPath('rootEntity')->addViolation();
        } elseif ($root->isPlaceholder()) {
            $context->buildViolation('A placeholder cannot be the root entity')->atPath('rootEntity')->addViolation();
        } elseif (null !== $root->getParent() || !$root->getChildren()->isEmpty()) {
            $context->buildViolation('The root entity must not be placed in the hierarchy yet')->atPath('rootEntity')->addViolation();
        }
    }
}
