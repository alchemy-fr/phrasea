<?php

declare(strict_types=1);

namespace App\Attribute\Type;

class HtmlAttributeType extends CodeAttributeType
{
    public const string NAME = 'html';

    public function __construct(private readonly \HTMLPurifier $HTMLPurifier)
    {
    }

    public function convertToDbValue(mixed $value): ?string
    {
        $value = parent::convertToDbValue($value);

        return $this->purify($value);
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
