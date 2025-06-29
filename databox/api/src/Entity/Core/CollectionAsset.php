<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Alchemy\ESBundle\Indexer\ESIndexableDeleteDependencyInterface;
use Alchemy\ESBundle\Indexer\ESIndexableDependencyInterface;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use App\Entity\Traits\ExtraMetadataTrait;
use App\Repository\Core\CollectionAssetRepository;
use App\Validator\SameWorkspaceConstraint;
use Doctrine\ORM\Mapping as ORM;

#[ApiResource(
    shortName: 'collection-asset',
    operations: [
        new Delete(security: 'is_granted("DELETE", object)'),
        new Post(securityPostDenormalize: 'is_granted("CREATE", object)'),
    ],
)]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uniq_coll_asset', columns: ['collection_id', 'asset_id'])]
#[ORM\Entity(repositoryClass: CollectionAssetRepository::class)]
#[SameWorkspaceConstraint(
    properties: ['asset.workspace', 'collection.workspace']
)]
class CollectionAsset extends AbstractUuidEntity implements ESIndexableDependencyInterface, ESIndexableDeleteDependencyInterface, \Stringable
{
    use CreatedAtTrait;
    use ExtraMetadataTrait;

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

    public function getIndexableDeleteDependencies(): array
    {
        return [
            $this->asset,
        ];
    }
}
