<?php

declare(strict_types=1);

namespace App\Entity\Core;

use App\Entity\AbstractUuidEntity;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
class Workspace extends AbstractUuidEntity
{
    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $name = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function __toString()
    {
        return $this->getName();
    }
}
