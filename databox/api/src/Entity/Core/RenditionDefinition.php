<?php

declare(strict_types=1);

namespace App\Entity\Core;

use ApiPlatform\Core\Annotation\ApiProperty;
use App\Entity\AbstractUuidEntity;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Entity\Traits\WorkspaceTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Table]
#[ORM\Index(name: 'rend_def_ws_name', columns: ['workspace_id', 'name'])]
#[ORM\Entity]
class RenditionDefinition extends AbstractUuidEntity implements \Stringable
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    use WorkspaceTrait;

    /**
     * Override trait for annotation.
     */
    #[ORM\ManyToOne(targetEntity: Workspace::class, inversedBy: 'renditionDefinitions')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['_'])]
    protected ?Workspace $workspace = null;

    #[Groups(['renddef:index', 'renddef:read', 'renddef:write'])]
    #[ORM\Column(type: 'string', length: 80)]
    private ?string $name = null;

    #[Groups(['renddef:index', 'renddef:read', 'renddef:write'])]
    #[ORM\ManyToOne(targetEntity: RenditionClass::class, inversedBy: 'definitions')]
    #[ORM\JoinColumn(nullable: false)]
    protected ?RenditionClass $class = null;

    #[Groups(['renddef:index', 'renddef:read', 'renddef:write'])]
    #[ORM\Column(type: 'boolean')]
    private bool $download = true;

    #[Groups(['renddef:index', 'renddef:read', 'renddef:write'])]
    #[ORM\Column(type: 'boolean')]
    #[ApiProperty(security: "is_granted('READ_ADMIN', object)")]
    private bool $pickSourceFile = false;

    #[Groups(['renddef:index', 'renddef:read', 'renddef:write'])]
    #[ORM\Column(type: 'boolean')]
    #[ApiProperty(security: "is_granted('READ_ADMIN', object)")]
    private bool $useAsOriginal = false;

    #[Groups(['renddef:index', 'renddef:read', 'renddef:write'])]
    #[ORM\Column(type: 'boolean')]
    #[ApiProperty(security: "is_granted('READ_ADMIN', object)")]
    private bool $useAsPreview = false;

    #[Groups(['renddef:index', 'renddef:read', 'renddef:write'])]
    #[ORM\Column(type: 'boolean')]
    #[ApiProperty(security: "is_granted('READ_ADMIN', object)")]
    private bool $useAsThumbnail = false;

    #[Groups(['renddef:index', 'renddef:read', 'renddef:write'])]
    #[ORM\Column(type: 'boolean')]
    #[ApiProperty(security: "is_granted('READ_ADMIN', object)")]
    private bool $useAsThumbnailActive = false;

    #[Groups(['renddef:index', 'renddef:read', 'renddef:write'])]
    #[ORM\Column(type: 'text')]
    #[ApiProperty(security: "is_granted('READ_ADMIN', object)")]
    private ?string $definition = '';

    #[Groups(['renddef:index', 'renddef:read', 'renddef:write'])]
    #[ORM\Column(type: 'smallint', nullable: false)]
    #[ApiProperty(security: "is_granted('READ_ADMIN', object)")]
    private int $priority = 0;

    /**
     * @var AssetRendition[]
     */
    #[ORM\OneToMany(targetEntity: AssetRendition::class, mappedBy: 'definition', cascade: ['remove'])]
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
