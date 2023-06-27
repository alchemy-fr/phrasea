<?php

declare(strict_types=1);

namespace App\Entity\Core;

use App\Entity\AbstractUuidEntity;
use App\Entity\Traits\CreatedAtTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Table]
#[ORM\Entity]
class AssetFileVersion extends AbstractUuidEntity
{
    use CreatedAtTrait;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $versionName = null;

    #[ORM\ManyToOne(targetEntity: Asset::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['assetfileversion:index'])]
    private ?Asset $asset = null;

    #[ORM\ManyToOne(targetEntity: File::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['assetfileversion:index'])]
    private ?File $file = null;

    #[ORM\Column(type: 'json')]
    private array $context = [];

    public function getVersionName(): ?string
    {
        return $this->versionName;
    }

    public function setVersionName(?string $versionName): void
    {
        $this->versionName = $versionName;
    }

    #[Groups(['assetfileversion:index'])]
    public function getName(): string
    {
        return $this->versionName ?? 'v-'.$this->createdAt->format('Y-m-d_H-i-s');
    }

    public function getAsset(): ?Asset
    {
        return $this->asset;
    }

    public function setAsset(?Asset $asset): void
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

    public function getContext(): array
    {
        return $this->context;
    }

    public function setContext(array $context): void
    {
        $this->context = $context;
    }
}
