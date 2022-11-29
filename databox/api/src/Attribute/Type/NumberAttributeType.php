<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use Elastica\Query;
use Elastica\Query\AbstractQuery;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class NumberAttributeType extends AbstractAttributeType
{
    public const NAME = 'number';

    public static function getName(): string
    {
        return self::NAME;
    }

    public function getElasticSearchType(): string
    {
        return 'long';
    }

    /**
     * @param int|float|string $value
     *
     * @return float
     */
    public function normalizeValue($value)
    {
        return (float) $value;
    }

    public function validate($value, ExecutionContextInterface $context): void
    {
        if (null === $value) {
            return;
        }

        if (!is_numeric($value)) {
            $context->addViolation('Invalid number');
        }
    }

    public function supportsAggregation(): bool
    {
        return true;
    }

    public function createFilterQuery(string $field, $value): AbstractQuery
    {
        return new Query\Terms($field, $value);
    }
}
