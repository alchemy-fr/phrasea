<?php

declare(strict_types=1);

namespace App\Attribute\Type;

class HtmlAttributeType extends CodeAttributeType
{
    public const string NAME = 'html';

    public function __construct(private readonly \HTMLPurifier $HTMLPurifier)
    {
    }

    public function normalizeValue(mixed $value): mixed
    {
        if (is_string($value)) {
            $value = $this->HTMLPurifier->purify($value);
        }

        return parent::normalizeValue($value);
    }

    public function denormalizeValue($value): mixed
    {
        return $this->purify($value);
    }

    public function normalizeElasticsearchValue(?string $value): mixed
    {
        if (null === $value) {
            return null;
        }

        return strip_tags($value);
    }

    private function purify(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        return $this->HTMLPurifier->purify($value);
    }

    public function supportsAggregation(): bool
    {
        return false;
    }
}
