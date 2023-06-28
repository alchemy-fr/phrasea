<?php

declare(strict_types=1);

namespace App\Entity\Core;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\Entity\AbstractUuidEntity;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Repository\Core\AssetRenditionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uniq_representation', columns: ['definition_id', 'asset_id'])]
#[ORM\Entity(repositoryClass: AssetRenditionRepository::class)]
class AssetRendition extends AbstractUuidEntity
{
    use CreatedAtTrait;
    use UpdatedAtTrait;

    #[Groups(['rendition:index', 'rendition:read'])]
    #[ORM\ManyToOne(targetEntity: RenditionDefinition::class, inversedBy: 'renditions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?RenditionDefinition $definition = null;

    #[Groups(['rendition:index', 'rendition:read'])]
    #[ORM\ManyToOne(targetEntity: Asset::class, inversedBy: 'renditions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Asset $asset = null;

    #[Groups(['rendition:index', 'rendition:read', 'asset:index', 'asset:read'])]
    #[ORM\ManyToOne(targetEntity: File::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?File $file = null;

    public function getAsset(): Asset
    {
        return $this->asset;
    }

    public function setAsset(Asset $asset): void
    {
        $this->asset = $asset;
        $asset->getRenditions()->add($this);
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

    #[ApiProperty]
    #[Groups(['rendition:index', 'rendition:read', 'asset:index', 'asset:read'])]
    public function getName(): string
    {
        return $this->definition->getName();
    }

    public function isReady(): bool
    {
        return null !== $this->file;
    }
}
