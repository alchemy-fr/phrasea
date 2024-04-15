<?php

declare(strict_types=1);

namespace App\Entity\Expose;

use App\Entity\AbstractUuidEntity;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;


#[ORM\Table]
#[ORM\Entity]
class ExposeInstance extends AbstractUuidEntity
{
    use CreatedAtTrait;
    use UpdatedAtTrait;

    #[ORM\Column(type: Types::STRING, length: 100, unique: true, nullable: false)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 100)]
    private ?string $name = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
    #[Assert\Url]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $url = null;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): void
    {
        $this->url = $url;
    }

    public function __toString(): string
    {
        return $this->getName() ?? $this->getId();
    }
}
