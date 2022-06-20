<?php

declare(strict_types=1);

namespace App\Entity;

interface ESIndexableInterface
{
    public function isObjectIndexable(): bool;
}
