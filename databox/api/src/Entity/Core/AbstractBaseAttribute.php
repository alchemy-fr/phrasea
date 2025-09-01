<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Alchemy\CoreBundle\Entity\Traits\UpdatedAtTrait;
use App\Validator\ValidAttributeConstraint;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\MappedSuperclass]
#[ValidAttributeConstraint]
abstract class AbstractBaseAttribute extends AbstractUuidEntity
{
    use CreatedAtTrait;
    use UpdatedAtTrait;

    #[ORM\Column(type: Types::STRING, length: 10, nullable: true)]
    protected ?string $locale = null;

    #[ORM\Column(type: Types::INTEGER, nullable: false)]
    private int $position = 0;

    #[ORM\Column(type: Types::TEXT, nullable: false)]
    #[Assert\NotNull]
    private ?string $value = null;

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): void
    {
        $this->value = $value;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function hasLocale(): bool
    {
        return null !== $this->locale;
    }

    public function setLocale(?string $locale): void
    {
        if (empty($locale)) {
            $this->locale = null;
        } else {
            $this->locale = $locale;
        }
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
