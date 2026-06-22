<?php

declare(strict_types=1);

namespace App\Attribute\Type;

final class BooleanAttributeType extends AbstractAttributeType
{
    final public const string NAME = 'boolean';

    public static function getName(): string
    {
        return self::NAME;
    }

    public function getElasticSearchType(): string
    {
        return 'boolean';
    }

    #[\Override]
    public function normalizeValue(mixed $value): mixed
    {
        if (is_bool($value)) {
            return $value;
        } elseif (is_string($value) || $value instanceof \Stringable) {
            $value = (string) $value;

            if (in_array(strtolower($value), [
                'y',
                'yes',
                '1',
                'true',
                'on',
            ], true)) {
                return true;
            } elseif (in_array(strtolower($value), [
                'n',
                'no',
                '0',
                'false',
                'off',
            ], true)) {
                return false;
            }
        } elseif (1 === $value) {
            return true;
        } elseif (0 === $value) {
            return false;
        }

        if (is_string($value)) {
            $value = trim($value);
        }

        return parent::normalizeValue($value);
    }

    #[\Override]
    public function convertToDbValue(mixed $value): ?string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return parent::convertToDbValue($value);
    }

    #[\Override]
    public function denormalizeValue(?string $value): mixed
    {
        if ('1' === $value) {
            return true;
        } elseif ('0' === $value) {
            return false;
        }

        return null;
    }

    #[\Override]
    public function getStringValue(?string $value, ?string $locale): string
    {
        $bool = $this->denormalizeValue($value);
        if (null === $bool) {
            return '';
        }

        return $bool ? 'true' : 'false';
    }

    #[\Override]
    public function normalizeElasticsearchValue(?string $value): mixed
    {
        if ('1' === $value) {
            return true;
        } elseif ('0' === $value) {
            return false;
        }

        return null;
    }

    public function validate(mixed $value): ?array
    {
        if (!is_bool($value)) {
            return ['Invalid boolean'];
        }

        return null;
    }

    #[\Override]
    public function supportsAggregation(): bool
    {
        return true;
    }
}
