<?php

declare(strict_types=1);

namespace App\Entity;

interface ObjectDisplayableNameInterface
{
    public function getObjectDisplayName(): string;
}
