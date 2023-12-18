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
use App\Api\Provider\RenditionDefinitionCollectionProvider;
use App\Controller\Core\RenditionDefinitionSortAction;
use App\Entity\AbstractUuidEntity;

use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Entity\Traits\WorkspaceTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'rendition-definition',
    operations: [
        new Get(security: 'is_granted("READ", object)'),
        new Delete(security: 'is_granted("DELETE", object)'),
        new Put(security: 'is_granted("EDIT", object)'),
        new Patch(security: 'is_granted("EDIT", object)'),
        new GetCollection(),
        new Post(securityPostDenormalize: 'is_granted("CREATE", object)'),
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
    order: ['priority' => 'DESC'],
    provider: RenditionDefinitionCollectionProvider::class,
)]

#[ORM\Table]
#[ORM\Index(columns: ['workspace_id', 'name'], name: 'rend_def_ws_name')]
#[ORM\Entity]
class RenditionDefinition extends AbstractUuidEntity implements \Stringable
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    use WorkspaceTrait;
    final public const GROUP_READ = 'renddef:read';
    final public const GROUP_LIST = 'renddef:index';
    final public const GROUP_WRITE = 'renddef:w';

    /**
     * Override trait for annotation.
     */
    #[ORM\ManyToOne(targetEntity: Workspace::class, inversedBy: 'renditionDefinitions')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['_'])]
    protected ?Workspace $workspace = null;

    #[Groups([RenditionDefinition::GROUP_LIST, RenditionDefinition::GROUP_READ, RenditionDefinition::GROUP_WRITE])]
    #[ORM\Column(type: Types::STRING, length: 80)]
    private ?string $name = null;

    #[Groups([RenditionDefinition::GROUP_LIST, RenditionDefinition::GROUP_READ, RenditionDefinition::GROUP_WRITE])]
    #[ORM\ManyToOne(targetEntity: RenditionClass::class, inversedBy: 'definitions')]
    #[ORM\JoinColumn(nullable: false)]
    protected ?RenditionClass $class = null;

    #[Groups([RenditionDefinition::GROUP_LIST, RenditionDefinition::GROUP_READ, RenditionDefinition::GROUP_WRITE])]
    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $download = true;

    #[Groups([RenditionDefinition::GROUP_LIST, RenditionDefinition::GROUP_READ, RenditionDefinition::GROUP_WRITE])]
    #[ORM\Column(type: Types::BOOLEAN)]
    #[ApiProperty(security: "is_granted('READ_ADMIN', object)")]
    private bool $pickSourceFile = false;

    #[Groups([RenditionDefinition::GROUP_LIST, RenditionDefinition::GROUP_READ, RenditionDefinition::GROUP_WRITE])]
    #[ORM\Column(type: Types::BOOLEAN)]
    #[ApiProperty(security: "is_granted('READ_ADMIN', object)")]
    private bool $useAsOriginal = false;

    #[Groups([RenditionDefinition::GROUP_LIST, RenditionDefinition::GROUP_READ, RenditionDefinition::GROUP_WRITE])]
    #[ORM\Column(type: Types::BOOLEAN)]
    #[ApiProperty(security: "is_granted('READ_ADMIN', object)")]
    private bool $useAsPreview = false;

    #[Groups([RenditionDefinition::GROUP_LIST, RenditionDefinition::GROUP_READ, RenditionDefinition::GROUP_WRITE])]
    #[ORM\Column(type: Types::BOOLEAN)]
    #[ApiProperty(security: "is_granted('READ_ADMIN', object)")]
    private bool $useAsThumbnail = false;

    #[Groups([RenditionDefinition::GROUP_LIST, RenditionDefinition::GROUP_READ, RenditionDefinition::GROUP_WRITE])]
    #[ORM\Column(type: Types::BOOLEAN)]
    #[ApiProperty(security: "is_granted('READ_ADMIN', object)")]
    private bool $useAsThumbnailActive = false;

    #[Groups([RenditionDefinition::GROUP_LIST, RenditionDefinition::GROUP_READ, RenditionDefinition::GROUP_WRITE])]
    #[ORM\Column(type: Types::TEXT)]
    #[ApiProperty(security: "is_granted('READ_ADMIN', object)")]
    private ?string $definition = '';

    #[Groups([RenditionDefinition::GROUP_LIST, RenditionDefinition::GROUP_READ, RenditionDefinition::GROUP_WRITE])]
    #[ORM\Column(type: Types::SMALLINT, nullable: false)]
    #[ApiProperty(security: "is_granted('READ_ADMIN', object)")]
    private int $priority = 0;

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
        return $this->getName();
    }

    public function isDownload(): bool
    {
        return $this->download;
    }

    public function setDownload(bool $download): void
    {
        $this->download = $download;
    }

    public function isPickSourceFile(): bool
    {
        return $this->pickSourceFile;
    }

    public function setPickSourceFile(bool $pickSourceFile): void
    {
        $this->pickSourceFile = $pickSourceFile;
    }
}
