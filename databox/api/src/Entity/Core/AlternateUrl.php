<?php

declare(strict_types=1);

namespace App\Entity\Core;

use App\Entity\AbstractUuidEntity;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\WorkspaceTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\Core\AttributeRepository::class)]
class AlternateUrl extends AbstractUuidEntity
{
    use CreatedAtTrait;
    use WorkspaceTrait;

    #[ORM\Column(type: 'string', length: 50, nullable: false)]
    private ?string $type = null;

    #[ORM\Column(type: 'string', length: 255, nullable: false)]
    private ?string $label = null;

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): void
    {
        $this->type = $type;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): void
    {
        $this->label = $label;
    }
}
