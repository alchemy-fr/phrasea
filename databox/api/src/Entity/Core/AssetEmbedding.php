<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Alchemy\CoreBundle\Entity\Traits\UpdatedAtTrait;
use Alchemy\ESBundle\Indexer\ESIndexableDependencyInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uniq_asset_embedding_asset', columns: ['asset_id'])]
#[ORM\Entity]
class AssetEmbedding extends AbstractUuidEntity implements ESIndexableDependencyInterface
{
    use CreatedAtTrait;
    use UpdatedAtTrait;

    #[ORM\OneToOne(targetEntity: Asset::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Asset $asset = null;

    /**
     * @var float[]
     */
    #[ORM\Column(type: Types::JSON)]
    private array $vector = [];

    #[ORM\Column(type: Types::STRING, length: 100)]
    private ?string $model = null;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $dims = 0;

    public function getAsset(): ?Asset
    {
        return $this->asset;
    }

    public function setAsset(?Asset $asset): void
    {
        $this->asset = $asset;
    }

    /**
     * @return float[]
     */
    public function getVector(): array
    {
        return $this->vector;
    }

    /**
     * @param float[] $vector
     */
    public function setVector(array $vector): void
    {
        $this->vector = $vector;
        $this->dims = count($vector);
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setModel(?string $model): void
    {
        $this->model = $model;
    }

    public function getDims(): int
    {
        return $this->dims;
    }
}
