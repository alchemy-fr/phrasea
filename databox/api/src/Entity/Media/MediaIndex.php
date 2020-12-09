<?php

declare(strict_types=1);

namespace App\Entity\Media;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Entity\AbstractUuidEntity;
use App\Entity\Core\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ApiResource()
 */
class MediaIndex extends AbstractUuidEntity
{
    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Core\Collection")
     * @ORM\JoinColumn(nullable=false)
     */
    private Collection $collection;

    public function getTitle(string $locale): string
    {
        return $this->collection->getTitle($locale);
    }
}
