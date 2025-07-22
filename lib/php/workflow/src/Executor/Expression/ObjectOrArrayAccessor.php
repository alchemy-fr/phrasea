<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Executor\Expression;

use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

readonly class ObjectOrArrayAccessor
{
    private PropertyAccessorInterface $propertyAccessor;

    public function __construct(private object|array $wrapped)
    {
        $this->propertyAccessor = PropertyAccess::createPropertyAccessorBuilder()
            ->disableExceptionOnInvalidIndex()
            ->disableExceptionOnInvalidPropertyPath()
            ->getPropertyAccessor();
    }

    public function __get(string $name): mixed
    {
        if (is_array($this->wrapped)) {
            return self::wrap($this->wrapped[$name] ?? null);
        }

        return self::wrap($this->propertyAccessor->getValue($this->wrapped, $name));
    }

    public static function wrap(mixed $value): mixed
    {
        if (is_array($value) || is_object($value)) {
            return new self($value);
        }

        return $value;
    }

    public function __serialize(): array
    {
        return [
            'wrapped' => $this->wrapped,
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->wrapped = $data['wrapped'];
        $this->propertyAccessor = PropertyAccess::createPropertyAccessorBuilder()
            ->disableExceptionOnInvalidIndex()
            ->disableExceptionOnInvalidPropertyPath()
            ->getPropertyAccessor();
    }

    public function unwrap(): object|array
    {
        return $this->wrapped;
    }
}
