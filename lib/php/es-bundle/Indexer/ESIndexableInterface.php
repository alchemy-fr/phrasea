<?php

declare(strict_types=1);

namespace Alchemy\ESBundle\Indexer;

interface ESIndexableInterface
{
    public function getId(): string;

    public function isObjectIndexable(): bool;
}
