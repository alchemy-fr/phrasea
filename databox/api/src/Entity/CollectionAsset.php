<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\CreatedAtTrait;
use Doctrine\ORM\Mapping as ORM;

class CollectionAsset extends AbstractUuidEntity
{
    use CreatedAtTrait;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Collection", inversedBy="assets")
     * @ORM\JoinColumn(nullable=false)
     */
    private Collection $collection;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Asset", inversedBy="collections")
     * @ORM\JoinColumn(nullable=false)
     */
    private Asset $asset;

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

}
