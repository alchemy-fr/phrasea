<?php

declare(strict_types=1);

namespace App\Entity\Core;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Entity\AbstractUuidEntity;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Entity\Traits\WorkspaceTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity()
 * @ORM\Table(indexes={@ORM\Index(name="rend_def_ws_name", columns={"workspace_id", "name"})})
 * @ApiResource(
 *  shortName="rendition-definition",
 *  normalizationContext={"groups"={"_", "renddef:index"}},
 *  denormalizationContext={"groups"={"renddef:write"}},
 * )
 */
class RenditionDefinition extends AbstractUuidEntity
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    use WorkspaceTrait;

    /**
     * @Groups({"renddef:index", "renddef:read", "renddef:write"})
     * @ORM\Column(type="string", length=80)
     */
    private ?string $name = null;

    /**
     * @Groups({"renddef:index", "renddef:read", "renddef:write"})
     * @ORM\ManyToOne(targetEntity="RenditionClass")
     * @ORM\JoinColumn(nullable=true)
     */
    protected ?RenditionClass $class = null;

    /**
     * @Groups({"renddef:index", "renddef:read", "renddef:write"})
     * @ORM\Column(type="boolean")
     */
    private bool $useAsOriginal = false;

    /**
     * @Groups({"renddef:index", "renddef:read", "renddef:write"})
     * @ORM\Column(type="boolean")
     */
    private bool $useAsPreview = false;

    /**
     * @Groups({"renddef:index", "renddef:read", "renddef:write"})
     * @ORM\Column(type="boolean")
     */
    private bool $useAsThumbnail = false;

    /**
     * @Groups({"renddef:index", "renddef:read", "renddef:write"})
     * @ORM\Column(type="boolean")
     */
    private bool $useAsThumbnailActive = false;

    /**
     * @Groups({"renddef:index", "renddef:read", "renddef:write"})
     * @ORM\Column(type="text")
     */
    private ?string $definition = '';

    /**
     * @Groups({"renddef:index", "renddef:read", "renddef:write"})
     * @ORM\Column(type="smallint", nullable=false)
     */
    private int $priority = 0;

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

    public function __toString()
    {
        return $this->getName();
    }
}