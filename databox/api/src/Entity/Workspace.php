<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiProperty;
use Symfony\Component\Serializer\Annotation\Groups;
use Doctrine\ORM\Mapping as ORM;

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
