<?php

declare(strict_types=1);

namespace App\Attribute;

use App\Attribute\Type\AttributeTypeInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class AttributeTypeRegistry
{
    /**
     * @var AttributeTypeInterface[]
     */
    private array $types;

    public function __construct(
        #[AutowireIterator(AttributeTypeInterface::TAG, indexAttribute: 'key')]
        iterable $types,
    ) {
        $this->types = iterator_to_array($types);
    }

    public function getType(string $type): ?AttributeTypeInterface
    {
        return $this->types[$type] ?? null;
    }

    public function getStrictType(string $type): ?AttributeTypeInterface
    {
        if (!isset($this->types[$type])) {
            throw new \InvalidArgumentException(sprintf('Attribute type "%s" not found', $type));
        }

        return $this->getType($type);
    }

    /**
     * @return AttributeTypeInterface[]
     */
    public function getTypes(): array
    {
        return $this->types;
    }
}
