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

class DateTimeAttributeType extends AbstractAttributeType
{
    public static function getName(): string
    {
        return 'date_time';
    }

    public function supportsAggregation(): bool
    {
        return true;
    }

    public function getGroupValueLabel($value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        return $value ?? '';
    }

    public function createFilterQuery(string $field, $value): AbstractQuery
    {
        $startFloor = new DateTime();
        $startFloor->setTimestamp((int) $value[0]);

        $endCeil = new DateTime();
        $endCeil->setTimestamp((int) $value[1]);

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

    public function normalizeValue($value): ?string
    {
        if (!$value instanceof DateTimeInterface) {
            if (empty($value)) {
                return null;
            }

            if (empty(trim($value))) {
                return null;
            }

            if (10 === strlen($value)) {
                $value .= 'T00:00:00Z';
            }

            if (false === $value = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $value)) {
                return null;
            }
        }

        return $value->format(DateTimeInterface::ATOM);
    }

    /**
     * @return DateTimeImmutable|null
     */
    public function denormalizeValue(?string $value)
    {
        if (null === $value) {
            return null;
        }

        try {
            return DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $value);
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
