<?php

namespace Alchemy\StorageBundle\Storage;

interface PathGeneratorInterface
{
    public function generatePath(?string $extension, string $prefix = ''): string;
}
