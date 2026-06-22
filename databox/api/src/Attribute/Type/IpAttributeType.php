<?php

declare(strict_types=1);

namespace App\Attribute\Type;

final class IpAttributeType extends AbstractAttributeType
{
    final public const string NAME = 'ip';

    public static function getName(): string
    {
        return self::NAME;
    }

    public function getElasticSearchType(): string
    {
        return 'ip';
    }

    public function validate(mixed $value): ?array
    {
        if (null === $value) {
            return null;
        }

        if (!is_string($value)) {
            return ['Invalid IP address'];
        }

        if (!filter_var($value, FILTER_VALIDATE_IP)) {
            return ['Invalid IP address'];
        }

        return null;
    }

    #[\Override]
    public function supportsAggregation(): bool
    {
        return true;
    }

    #[\Override]
    public function supportsSuggest(): bool
    {
        return true;
    }
}
