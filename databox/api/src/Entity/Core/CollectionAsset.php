<?php

declare(strict_types=1);

namespace App\Entity\Core;

use ApiPlatform\Metadata\ApiResource;
use App\Entity\AbstractUuidEntity;
use App\Entity\SearchDependencyInterface;
use App\Entity\Traits\CreatedAtTrait;
use App\Repository\Core\CollectionAssetRepository;
use Doctrine\ORM\Mapping as ORM;

#[ApiResource]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uniq_coll_asset', columns: ['collection_id', 'asset_id'])]
#[ORM\Entity(repositoryClass: CollectionAssetRepository::class)]
class CollectionAsset extends AbstractUuidEntity implements SearchDependencyInterface, \Stringable
{
    use CreatedAtTrait;

    #[ORM\ManyToOne(targetEntity: Collection::class, inversedBy: 'assets')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Collection $collection = null;

    #[ORM\ManyToOne(targetEntity: Asset::class, inversedBy: 'collections')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Asset $asset = null;

    public function getCollection(): Collection
    {
        return $this->collection;
    }

    public function setCollection(Collection $collection): void
    {
        $this->collection = $collection;
    }

    public function getAsset(): Asset
    {
        return $this->asset;
    }

    public function setAsset(Asset $asset): void
    {
        $this->asset = $asset;
    }

    public function __toString(): string
    {
        return sprintf('C(%s) <> A(%s)', $this->collection->getId(), $this->asset->getId());
    }
}
