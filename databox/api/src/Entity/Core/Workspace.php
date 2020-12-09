<?php

declare(strict_types=1);

namespace App\Entity\Core;

use ApiPlatform\Core\Annotation\ApiProperty;
use App\Entity\AbstractUuidEntity;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity()
 */
class Workspace extends AbstractUuidEntity
{
    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @ApiProperty(writable=false)
     * @Groups({"workspace_read"})
     */
    private string $name;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}
