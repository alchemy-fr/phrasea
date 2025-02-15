<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\WorkspaceTrait;
use App\Repository\Core\AttributeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AttributeRepository::class)]
class AlternateUrl extends AbstractUuidEntity
{
    use CreatedAtTrait;
    use WorkspaceTrait;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: false)]
    private ?string $type = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
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
