<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use HTMLPurifier;

class HtmlAttributeType extends CodeAttributeType
{
    public function __construct(private readonly HTMLPurifier $HTMLPurifier)
    {
    }

    public static function getName(): string
    {
        return 'html';
    }

    public function normalizeValue($value): ?string
    {
        $value = parent::normalizeValue($value);

        return $this->purify($value);
    }

    public function denormalizeValue($value): ?string
    {
        return $this->purify($value);
    }

    public function normalizeElasticsearchValue(?string $value)
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
