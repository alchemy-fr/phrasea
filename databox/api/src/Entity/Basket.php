<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ApiResource()
 */
class Basket extends AbstractUuidEntity
{
    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Collection")
     * @ORM\JoinColumn(nullable=false)
     */
    private Collection $collection;

    public function getTitle(string $locale): string
    {
        return $this->collection->getTitle($locale);
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
