<?php

declare(strict_types=1);

namespace App\Entity;

interface MergeableValueObjectInterface
{
    /**
     * applyDefaults and mergeWith methods are here to prevent
     * instantiating new Config from Symfony denormalization (from serializer component)
     * in PUT verb.
     */
    public function applyDefaults(): void;

    public function mergeWith(self $object): self;
}
