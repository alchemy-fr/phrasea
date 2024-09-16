<?php

namespace App\Integration\Core\Rendition;

final readonly class AssetAttributeAccessor implements \ArrayAccess
{
    public function __construct(
        private AssetMetadataContainer $container,
    )
    {
    }

    public function offsetExists(mixed $offset): bool
    {
        return null !== $this->container->getAttribute($offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->container->getAttribute($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \InvalidArgumentException('Readonly metadata accessor');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \InvalidArgumentException('Readonly metadata accessor');
    }
}
