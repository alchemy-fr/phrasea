<?php

declare(strict_types=1);

namespace App\Entity\Core;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use App\Entity\AbstractUuidEntity;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity()
 * @ApiResource()
 */
class MetadataValue extends AbstractUuidEntity
{
    use CreatedAtTrait;
    use UpdatedAtTrait;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Core\Workspace")
     * @ORM\JoinColumn(nullable=false)
     */
    private Workspace $workspace;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Core\Workspace")
     * @ORM\JoinColumn(nullable=false)
     */
    private Asset $asset;
}
