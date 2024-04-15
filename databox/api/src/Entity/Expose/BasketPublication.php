<?php

declare(strict_types=1);

namespace App\Entity\Expose;

use App\Entity\AbstractUuidEntity;
use App\Entity\Basket\Basket;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidType;
use Symfony\Component\Validator\Constraints as Assert;


#[ORM\Table]
#[ORM\Entity]
class BasketPublication extends AbstractUuidEntity
{
    use CreatedAtTrait;
    use UpdatedAtTrait;

    #[ORM\ManyToOne(targetEntity: ExposeInstance::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?ExposeInstance $instance = null;

    #[ORM\ManyToOne(targetEntity: Basket::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Basket $basket = null;

    #[ORM\Column(type: UuidType::NAME)]
    #[Assert\NotNull]
    private ?string $publicationId = null;

    public function getInstance(): ?ExposeInstance
    {
        return $this->instance;
    }

    public function setInstance(?ExposeInstance $instance): void
    {
        $this->instance = $instance;
    }

    public function getBasket(): ?Basket
    {
        return $this->basket;
    }

    public function setBasket(?Basket $basket): void
    {
        $this->basket = $basket;
    }

    public function getPublicationId(): ?string
    {
        return $this->publicationId;
    }

    public function setPublicationId(?string $publicationId): void
    {
        $this->publicationId = $publicationId;
    }
}
