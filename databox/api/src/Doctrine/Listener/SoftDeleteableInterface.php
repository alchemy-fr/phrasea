<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use DateTimeInterface;

interface SoftDeleteableInterface
{
    public function getDeletedAt(): ?DateTimeInterface;
}
