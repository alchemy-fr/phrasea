<?php

declare(strict_types=1);

namespace App\Entity\Traits;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

trait DeletedAtTrait
{
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): void
    {
        $this->deletedAt = $deletedAt;
    }

    /**
     * Used by ES.
     */
    public function isDeleted(): bool
    {
        return null !== $this->deletedAt;
    }
}
