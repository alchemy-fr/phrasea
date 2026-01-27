<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use Symfony\Component\Validator\Context\ExecutionContextInterface;

class BooleanAttributeType extends AbstractAttributeType
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

    public function normalizeValue($value): ?string
    {
        $bool = $this->castValue($value);
        if (null === $bool) {
            return null;
        }

        return $bool ? '1' : '0';
    }

    private function castValue($value): ?bool
    {
        if (null === $value) {
            return null;
        }

        if (is_string($value)) {
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

            return null;
        }

        return (bool) $value;
    }

    public function denormalizeValue(?string $value)
    {
        if (null === $value) {
            return null;
        }

        if ('1' === $value) {
            return true;
        }

        return false;
    }

    public function normalizeElasticsearchValue(?string $value)
    {
        if (null === $value) {
            return null;
        }

        return (bool) $value;
    }

    public function denormalizeElasticsearchValue($value): ?string
    {
        if (null === $value) {
            return null;
        }

        return $value ? '1' : '0';
    }

    public function validate($value, ExecutionContextInterface $context): void
    {
        if (null === $value) {
            return;
        }

        if (!is_bool($value)) {
            $context->addViolation('Invalid boolean');
        }
    }

    public function supportsAggregation(): bool
    {
        return true;
    }
}
