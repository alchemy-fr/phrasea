<?php

declare(strict_types=1);

namespace App\Entity\Basket;

use App\Entity\AbstractUuidEntity;
use App\Entity\Core\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Basket extends AbstractUuidEntity
{
    #[ORM\ManyToOne(targetEntity: Collection::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Collection $collection;

    public function getTitle(): ?string
    {
        return $this->collection->getTitle();
    }

    public function getCollection(): Collection
    {
        return $this->collection;
    }

    public function setCollection(Collection $collection): void
    {
        $this->collection = $collection;
    }
}
