<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use App\Elasticsearch\SearchType;

class KeywordAttributeType extends AbstractAttributeType
{
    public const string NAME = 'keyword';

    public static function getName(): string
    {
        return static::NAME;
    }

    public function getElasticSearchType(): string
    {
        return 'keyword';
    }

    public function getElasticSearchSearchType(): ?SearchType
    {
        return SearchType::Keyword;
    }

    #[\Override]
    public function isLocaleAware(): bool
    {
        return true;
    }

    #[\Override]
    public function supportsSuggest(): bool
    {
        return true;
    }

    public function validate(mixed $value): ?array
    {
        if (!is_string($value) && !(is_object($value) && method_exists($value, '__toString'))) {
            return ['Invalid value'];
        }

        return null;
    }

    #[\Override]
    public function supportsAggregation(): bool
    {
        return true;
    }
}
