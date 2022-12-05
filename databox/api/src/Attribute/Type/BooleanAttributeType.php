<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use Elastica\Query\AbstractQuery;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Elastica\Query;

class BooleanAttributeType extends AbstractAttributeType
{
    public const NAME = 'boolean';

    public static function getName(): string
    {
        return self::NAME;
    }

    public function getElasticSearchType(): string
    {
        return 'boolean';
    }

    public function normalizeValue($value)
    {
        return (bool) $value;
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

    public function createFilterQuery(string $field, $value): AbstractQuery
    {
        return new Query\Terms($field, $value);
    }

    public function supportsAggregation(): bool
    {
        return true;
    }
}
