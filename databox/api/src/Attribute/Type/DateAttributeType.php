<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use App\Elasticsearch\FacetInterface;
use App\Entity\Core\AttributeDefinition;
use DateTime;
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

    public function getGroupValueLabel($value): string
    {
        if ($value instanceof \DateTimeInterface) {
            if ($value instanceof \DateTimeImmutable) {
                $date = \DateTime::createFromImmutable($value);
            } else {
                $date = clone $value;
            }

            $date->setTime(0,0,0);

            return (string) $date->getTimestamp();
        }

        return $value ?? '';
    }


    public function createFilterQuery(string $field, $value): AbstractQuery
    {
        $startFloor = new DateTime();
        $startFloor->setTimestamp((int) $value[0]);
        $startFloor->setTime(0,0, 0);

        $endCeil = new DateTime();
        $endCeil->setTimestamp((int) $value[1]);
        $endCeil->setTime(23,59, 59);

        return new Range($field, [
            'gte' => $startFloor->getTimestamp() * 1000,
            'lte' => $endCeil->getTimestamp() * 1000,
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
