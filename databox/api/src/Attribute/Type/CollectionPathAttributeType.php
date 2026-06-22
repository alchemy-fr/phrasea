<?php

declare(strict_types=1);

namespace App\Attribute\Type;

final class CollectionPathAttributeType extends AbstractAttributeType
{
    final public const string NAME = 'collection_path';

    public static function getName(): string
    {
        return self::NAME;
    }

    public function getElasticSearchType(): string
    {
        return 'text';
    }

    #[\Override]
    public function getElasticSearchMapping(string $locale): ?array
    {
        return null;
    }

    #[\Override]
    public function isLocaleAware(): bool
    {
        return false;
    }

    public function validate(mixed $value): ?array
    {
        throw new \LogicException('Should never be called');
    }

    public function getAggregationField(): ?string
    {
        throw new \LogicException('Should never be called');
    }

    #[\Override]
    public function supportsAggregation(): bool
    {
        return true;
    }

    #[\Override]
    public function isListed(): bool
    {
        return false;
    }
}
