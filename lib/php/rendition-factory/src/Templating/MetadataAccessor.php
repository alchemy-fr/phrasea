<?php

namespace Alchemy\RenditionFactory\Templating;

use Alchemy\RenditionFactory\Transformer\TransformationContext;

final readonly class MetadataAccessor implements \ArrayAccess
{
    public function __construct(
        private TransformationContext $context,
    ) {
    }

    public function offsetExists(mixed $offset): bool
    {
        return null !== $this->context->getMetadata($offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->context->getMetadata($offset);
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
