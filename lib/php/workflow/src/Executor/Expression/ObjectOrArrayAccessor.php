<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Executor\Expression;

use Symfony\Component\PropertyAccess\PropertyAccess;

readonly class ObjectOrArrayAccessor
{
    private object|array $wrapped;

    public function __construct(object|array $wrapped)
    {
        $this->wrapped = $wrapped;
    }

    public function __get(string $name): mixed
    {
        if (is_array($this->wrapped)) {
            return self::wrap($this->wrapped[$name] ?? null);
        }

        $propertyAccessor = PropertyAccess::createPropertyAccessor();

        return self::wrap($propertyAccessor->getValue($this->wrapped, $name));
    }

    public static function wrap(mixed $value): mixed
    {
        if (is_array($value) || is_object($value)) {
            return new self($value);
        }

        return $value;
    }
}
