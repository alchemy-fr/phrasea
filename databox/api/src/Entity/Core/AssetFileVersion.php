<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;

use App\Api\Provider\AssetFileVersionCollectionProvider;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'asset-file-version',
    operations: [
        new Get(security: 'is_granted("READ", object)'),
        new Delete(security: 'is_granted("DELETE", object)'),
        new GetCollection(),
    ],
    normalizationContext: [
        'groups' => [
            AssetFileVersion::GROUP_LIST,
        ],
    ],
    provider: AssetFileVersionCollectionProvider::class,
)]
#[ORM\Table]
#[ORM\Entity]
class AssetFileVersion extends AbstractUuidEntity
{
    use CreatedAtTrait;
    final public const string GROUP_READ = 'afv:read';
    final public const string GROUP_LIST = 'afv:index';

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    private ?string $versionName = null;

    #[ORM\ManyToOne(targetEntity: Asset::class, inversedBy: 'fileVersions')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups([AssetFileVersion::GROUP_LIST])]
    private ?Asset $asset = null;

    #[ORM\ManyToOne(targetEntity: File::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups([AssetFileVersion::GROUP_LIST])]
    private ?File $file = null;

    #[ORM\Column(type: Types::JSON)]
    private array $context = [];

    public function getVersionName(): ?string
    {
        return $this->versionName;
    }

    public function setVersionName(?string $versionName): void
    {
        $this->versionName = $versionName;
    }

    #[Groups([AssetFileVersion::GROUP_LIST])]
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
