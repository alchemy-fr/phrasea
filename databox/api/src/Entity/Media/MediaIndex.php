<?php

declare(strict_types=1);

namespace App\Entity\Media;

use App\Entity\AbstractUuidEntity;
use App\Entity\Core\Collection;
use App\Entity\Traits\WorkspaceTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class MediaIndex extends AbstractUuidEntity
{
    use WorkspaceTrait;

    #[ORM\ManyToOne(targetEntity: \App\Entity\Core\Collection::class)]
    #[ORM\JoinColumn(nullable: false)]
    private readonly Collection $collection;

    public function getTitle(): ?string
    {
        return $this->collection->getTitle();
    }
}
