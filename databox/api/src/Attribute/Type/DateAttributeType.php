<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use App\Elasticsearch\FacetInterface;
use App\Entity\Core\AttributeDefinition;
use DateTimeImmutable;
use DateTimeInterface;
use Elastica\Query\AbstractQuery;
use Elastica\Query\Range;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Throwable;

class DateAttributeType extends AbstractAttributeType
{
    public static function getName(): string
    {
        return 'date';
    }

    public function supportsAggregation(): bool
    {
        return true;
    }

    public function createFilterQuery(string $field, $value): AbstractQuery
    {
        return new Range($field, [
            'gte' => $value[0] * 1000,
            'lte' => $value[1] * 1000,
        ]);
    }

    public function getFacetType(): string
    {
        return FacetInterface::TYPE_DATE_RANGE;
    }

    public function getElasticSearchType(): string
    {
        return 'date';
    }

    public function getElasticSearchMapping(string $locale, AttributeDefinition $definition): array
    {
        return [
            'fields' => [
                'text' => [
                    'type' => 'text',
                ],
            ],
        ];
    }

    /**
     * @param string|DateTimeInterface $value
     *
     * @return string|null
     */
    public function normalizeValue($value)
    {
        if (empty($value)) {
            return null;
        }

        if (is_string($value)) {
            if (empty(trim($value))) {
                return null;
            }

            if (strlen($value) === 10) {
                $value .= 'T00:00:00Z';
            }

            if (false === $value = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $value)) {
                return null;
            }
        } elseif (!$value instanceof DateTimeInterface) {
            return null;
        }

        return $value->format(DateTimeInterface::ATOM);
    }

    /**
     * @param string $value
     *
     * @return DateTimeImmutable|null
     */
    public function denormalizeValue($value)
    {
        try {
            return new DateTimeImmutable($value);
        } catch (Throwable $e) {
            return null;
        }
    }

    public function validate($value, ExecutionContextInterface $context): void
    {
        if (empty($value)) {
            return;
        }

        try {
            new DateTimeImmutable($value);
        } catch (\Exception $e) {
            $context->addViolation('Invalid date');

            return;
        }
    }
}
