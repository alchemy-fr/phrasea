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
 * @ORM\Table(indexes={@ORM\Index(name="sds_ws_name", columns={"workspace_id", "name"})})
 * @ApiResource(
 *  shortName="sub-definition-spec",
 *  normalizationContext={"groups"={"_", "subdefspec:index"}},
 *  denormalizationContext={"groups"={"subdefspec:write"}},
 * )
 */
class SubDefinitionSpec extends AbstractUuidEntity
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    use WorkspaceTrait;

    /**
     * @Groups({"subdefspec:index", "subdefspec:read"})
     * @ORM\Column(type="string", length=80)
     */
    private ?string $name = null;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Core\SubDefinitionClass")
     * @ORM\JoinColumn(nullable=true)
     */
    protected ?SubDefinitionClass $class = null;

    /**
     * @Groups({"subdefspec:read"})
     * @ORM\Column(type="boolean")
     */
    private bool $useAsOriginal = false;

    /**
     * @Groups({"subdefspec:read"})
     * @ORM\Column(type="boolean")
     */
    private bool $useAsPreview = false;

    /**
     * @Groups({"subdefspec:read"})
     * @ORM\Column(type="boolean")
     */
    private bool $useAsThumbnail = false;

    /**
     * @Groups({"subdefspec:read"})
     * @ORM\Column(type="boolean")
     */
    private bool $useAsThumbnailActive = false;

    /**
     * @Groups({"subdefspec:read"})
     * @ORM\Column(type="text")
     */
    private ?string $definition = '';

    /**
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

    public function getClass(): ?SubDefinitionClass
    {
        return $this->class;
    }

    public function setClass(?SubDefinitionClass $class): void
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
}
