<?php

namespace Alchemy\RenditionFactory\DTO\Metadata;

interface MetadataContainerInterface
{
    public function getMetadata(string $name): ?string;

    public function getTemplatingContext(): array;
}
