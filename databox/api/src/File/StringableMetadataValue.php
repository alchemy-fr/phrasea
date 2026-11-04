<?php

namespace App\File;

final readonly class StringableMetadataValue implements \Stringable
{
    final public const string MULTIVALUE_SEPARATOR = "\n";

    public function __construct(private array $values)
    {
    }

    public function getValues(): array
    {
        return $this->values;
    }

    public function getValue(): ?string
    {
        if (empty($this->values)) {
            return null;
        }

        return implode(self::MULTIVALUE_SEPARATOR, $this->values);
    }

    public function __toString(): string
    {
        return $this->getValue() ?? '';
    }
}
