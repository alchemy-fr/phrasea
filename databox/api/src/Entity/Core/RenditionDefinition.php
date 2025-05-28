<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Alchemy\CoreBundle\Entity\Traits\UpdatedAtTrait;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Api\Model\Input\RenditionDefinitionInput;
use App\Api\Model\Output\RenditionDefinitionOutput;
use App\Api\Provider\RenditionDefinitionCollectionProvider;
use App\Controller\Core\RenditionDefinitionSortAction;
use App\Entity\Traits\TranslationsTrait;
use App\Entity\Traits\WorkspaceTrait;
use App\Validator as CustomAssert;
use App\Validator\SameWorkspaceConstraint;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'rendition-definition',
    operations: [
        new Get(
            normalizationContext: [
                'groups' => [RenditionDefinition::GROUP_READ],
            ],
            security: 'is_granted("READ", object)'
        ),
        new Delete(security: 'is_granted("DELETE", object)'),
        new Put(
            normalizationContext: [
                'groups' => [RenditionDefinition::GROUP_READ],
            ],
            security: 'is_granted("EDIT", object)',
            input: RenditionDefinitionInput::class,
        ),
        new Patch(security: 'is_granted("EDIT", object)'),
        new GetCollection(),
        new Post(
            normalizationContext: [
                'groups' => [RenditionDefinition::GROUP_READ],
            ],
            securityPostDenormalize: 'is_granted("CREATE", object)'),
        new Post(
            uriTemplate: '/rendition-definitions/sort',
            controller: RenditionDefinitionSortAction::class,
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
            read: false,
            name: 'post_sort',
            provider: null
        ),
    ],
    normalizationContext: [
        'groups' => [RenditionDefinition::GROUP_LIST],
    ],
    denormalizationContext: [
        'groups' => [RenditionDefinition::GROUP_WRITE],
    ],
    input: RenditionDefinitionInput::class,
    output: RenditionDefinitionOutput::class,
    order: ['priority' => 'DESC'],
    provider: RenditionDefinitionCollectionProvider::class,
)]

#[ORM\Table]
#[ORM\Index(columns: ['workspace_id', 'name'], name: 'rend_def_ws_name')]
#[ORM\UniqueConstraint(name: 'uniq_rend_def_ws_key', columns: ['workspace_id', 'key'])]
#[ORM\Entity]
#[SameWorkspaceConstraint(
    properties: ['workspace', 'class.workspace', 'parent.workspace'],
)]
class RenditionDefinition extends AbstractUuidEntity implements \Stringable
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    use WorkspaceTrait;
    use TranslationsTrait;

    final public const int BUILD_MODE_NONE = 0;
    final public const int BUILD_MODE_PICK_SOURCE = 1;
    final public const int BUILD_MODE_CUSTOM = 2;

    public const array BUILD_MODE_CHOICES = [
        'None' => RenditionDefinition::BUILD_MODE_NONE,
        'Pick source file' => RenditionDefinition::BUILD_MODE_PICK_SOURCE,
        'Custom' => RenditionDefinition::BUILD_MODE_CUSTOM,
    ];

    final public const string GROUP_READ = 'renddef:read';
    final public const string GROUP_LIST = 'renddef:index';
    final public const string GROUP_WRITE = 'renddef:w';
    private const string GRANT_ADMIN_PROP = "object ? is_granted('READ_ADMIN', object) : true";

    /**
     * Override trait for annotation.
     */
    #[ORM\ManyToOne(targetEntity: Workspace::class, inversedBy: 'renditionDefinitions')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    protected ?Workspace $workspace = null;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true)]
    protected ?self $parent = null;

    /**
     * Unique key by workspace. Used to prevent duplicates.
     */
    #[ORM\Column(type: Types::STRING, length: 150, nullable: true)]
    private ?string $key = null;

    #[ORM\Column(type: Types::STRING, length: 80)]
    #[Assert\NotNull]
    private ?string $name = null;

    #[ORM\ManyToOne(targetEntity: RenditionClass::class, inversedBy: 'definitions')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    protected ?RenditionClass $class = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $download = true;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $substitutable = true;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $buildMode = self::BUILD_MODE_NONE;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $useAsOriginal = false;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $useAsPreview = false;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $useAsThumbnail = false;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $useAsThumbnailActive = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[CustomAssert\ValidRenditionDefinitionConstraint]
    private ?string $definition = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: false)]
    #[Assert\NotNull]
    private int $priority = 0;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $labels = null;

    /**
     * @var AssetRendition[]
     */
    #[ORM\OneToMany(mappedBy: 'definition', targetEntity: AssetRendition::class, cascade: ['remove'])]
    protected ?DoctrineCollection $renditions = null;

    public function __construct()
    {
        parent::__construct();

        $this->renditions = new ArrayCollection();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function isUseAsOriginal(): bool
    {
        return $this->useAsOriginal;
    }

    public function setUseAsOriginal(bool $useAsOriginal): void
    {
        $this->useAsOriginal = $useAsOriginal;
    }

    public function isUseAsPreview(): bool
    {
        return $this->useAsPreview;
    }

    public function setUseAsPreview(bool $useAsPreview): void
    {
        $this->useAsPreview = $useAsPreview;
    }

    public function isUseAsThumbnail(): bool
    {
        return $this->useAsThumbnail;
    }

    public function setUseAsThumbnail(bool $useAsThumbnail): void
    {
        $this->useAsThumbnail = $useAsThumbnail;
    }

    public function isUseAsThumbnailActive(): bool
    {
        return $this->useAsThumbnailActive;
    }

    public function setUseAsThumbnailActive(bool $useAsThumbnailActive): void
    {
        $this->useAsThumbnailActive = $useAsThumbnailActive;
    }

    public function getDefinition(): ?string
    {
        return $this->definition;
    }

    public function setDefinition(?string $definition): void
    {
        $this->definition = $definition;
    }

    public function getClass(): ?RenditionClass
    {
        return $this->class;
    }

    public function setClass(?RenditionClass $class): void
    {
        $this->class = $class;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
    }

    public function __toString(): string
    {
        if (null !== $name = $this->getName()) {
            return sprintf('%s (%s)', $name, $this->getWorkspace()->getName());
        }

        return $this->getId();
    }

    public function isDownload(): bool
    {
        return $this->download;
    }

    public function setDownload(bool $download): void
    {
        $this->download = $download;
    }

    public function getBuildMode(): int
    {
        return $this->buildMode;
    }

    public function setBuildMode(int $buildMode): void
    {
        $this->buildMode = $buildMode;
    }

    public function getKey(): ?string
    {
        return $this->key;
    }

    public function setKey(?string $key): void
    {
        $this->key = $key;
    }

    public function getLabels(): ?array
    {
        return $this->labels;
    }

    public function setLabels(?array $labels): void
    {
        $this->labels = $labels;
    }

    public function isSubstitutable(): bool
    {
        return $this->substitutable;
    }

    public function setSubstitutable(bool $substitutable): void
    {
        $this->substitutable = $substitutable;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): void
    {
        if ($parent === $this) {
            throw new \InvalidArgumentException('Parent cannot be the same as the definition');
        }

        $this->parent = $parent;
    }
}
