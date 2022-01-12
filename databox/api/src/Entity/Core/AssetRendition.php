<?php

declare(strict_types=1);

namespace App\Entity\Core;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use App\Entity\AbstractUuidEntity;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass="App\Repository\Core\AssetRepresentationRepository")
 * @ORM\Table(uniqueConstraints={@ORM\UniqueConstraint(name="uniq_representation",columns={"definition_id", "asset_id"})})
 * @ApiResource()
 */
class AssetRendition extends AbstractUuidEntity
{
    use CreatedAtTrait;
    use UpdatedAtTrait;

    /**
     * @Groups({"rendition:index", "rendition:read"})
     * @ORM\ManyToOne(targetEntity="RenditionDefinition")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?RenditionDefinition $definition = null;

    /**
     * @Groups({"rendition:index", "rendition:read"})
     * @ORM\ManyToOne(targetEntity="App\Entity\Core\Asset")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?Asset $asset = null;

    /**
     * @Groups({"rendition:index", "rendition:read"})
     * @ORM\ManyToOne(targetEntity="App\Entity\Core\File")
     * @ORM\JoinColumn(nullable=true)
     */
    private ?File $file = null;

    /**
     * @Groups({"rendition:index", "rendition:read"})
     * @ORM\Column(type="boolean")
     */
    private bool $ready = false;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    protected ?string $uri = null;

    public function getAsset(): Asset
    {
        return $this->asset;
    }

    public function setAsset(Asset $asset): void
    {
        $this->asset = $asset;
    }

    public function getFile(): ?File
    {
        return $this->file;
    }

    public function setFile(?File $file): void
    {
        $this->file = $file;
    }

    public function getDefinition(): RenditionDefinition
    {
        return $this->definition;
    }

    public function setDefinition(RenditionDefinition $definition): void
    {
        $this->definition = $definition;
    }

    /**
     * @ApiProperty()
     * @Groups({"rendition:index", "rendition:read"})
     */
    public function getName(): string
    {
        return $this->definition->getName();
    }

    public function isReady(): bool
    {
        return $this->ready;
    }

    public function setReady(bool $ready): void
    {
        $this->ready = $ready;
    }

    public function getUri(): ?string
    {
        return $this->uri;
    }

    public function setUri(?string $uri): void
    {
        $this->uri = $uri;
    }
}
