<?php

declare(strict_types=1);

namespace App\Entity;

interface MergeableValueObjectInterface
{
    public function mergeWith(self $object): self;
}
