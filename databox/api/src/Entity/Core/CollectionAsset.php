<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Alchemy\ESBundle\Indexer\ESIndexableDeleteDependencyInterface;
use Alchemy\ESBundle\Indexer\ESIndexableDependencyInterface;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use App\Api\Provider\CollectionAssetCollectionProvider;
use App\Api\Provider\StoryAssetCollectionProvider;
use App\Entity\Traits\ExtraMetadataTrait;
use App\Repository\Core\CollectionAssetRepository;
use App\Validator\SameWorkspaceConstraint;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    shortName: 'collection-asset',
    operations: [
        new Delete(security: 'is_granted("DELETE", object)'),
        new Post(securityPostDenormalize: 'is_granted("CREATE", object)'),
        new GetCollection(
            uriTemplate: '/collections/{id}/assets',
            description: 'List the assets of a collection, in their stored order',
            name: 'collection_assets',
            provider: CollectionAssetCollectionProvider::class,
        ),
        new GetCollection(
            uriTemplate: '/assets/{id}/story-assets',
            description: 'List the assets of a story, in their stored order',
            name: 'story_assets',
            provider: StoryAssetCollectionProvider::class,
        ),
    ],
    normalizationContext: [
        'groups' => [
            Asset::GROUP_LIST,
            self::GROUP_LIST,
        ],
    ],
    parameters: [
        'limit' => new QueryParameter(),
        'page' => new QueryParameter(),
    ],
)]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uniq_coll_asset', columns: ['collection_id', 'asset_id'])]
#[ORM\Index(name: 'idx_coll_asset_position', columns: ['collection_id', 'position'])]
#[UniqueEntity(
    fields: ['collection', 'asset'],
    message: 'This asset is already part of the collection.'
)]
#[ORM\Entity(repositoryClass: CollectionAssetRepository::class)]
#[SameWorkspaceConstraint(
    properties: ['asset.workspace', 'collection.workspace']
)]
class CollectionAsset extends AbstractUuidEntity implements ESIndexableDependencyInterface, ESIndexableDeleteDependencyInterface, \Stringable
{
    use CreatedAtTrait;
    use ExtraMetadataTrait;

    final public const string GROUP_LIST = 'collection-asset:list';

    #[ORM\ManyToOne(targetEntity: Collection::class, inversedBy: 'assets')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Collection $collection = null;

    #[ORM\ManyToOne(targetEntity: Asset::class, inversedBy: 'collections')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups([self::GROUP_LIST])]
    private ?Asset $asset = null;

    /**
     * Rank of the asset inside its collection, starting at 0.
     *
     * Left null until persisted: CollectionAssetPositionListener then appends the
     * relation at the end of the collection.
     */
    #[ORM\Column(type: Types::INTEGER, nullable: false)]
    #[Groups([self::GROUP_LIST])]
    private ?int $position = null;

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

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
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
