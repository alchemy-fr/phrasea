<?php

declare(strict_types=1);

namespace App\Entity\Core;

use App\Entity\AbstractUuidEntity;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\TranslatableTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Entity\TranslatableInterface;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
class MetadataValue extends AbstractUuidEntity implements TranslatableInterface
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    use TranslatableTrait;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Core\Workspace")
     * @ORM\JoinColumn(nullable=false)
     */
    private Asset $asset;
}
