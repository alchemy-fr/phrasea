<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

interface SoftDeleteableInterface
{
    public function getDeletedAt(): ?\DateTimeInterface;
}
