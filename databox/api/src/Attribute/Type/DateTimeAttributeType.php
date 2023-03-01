<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use App\Elasticsearch\ESFacetInterface;
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
        return ESFacetInterface::TYPE_DATE_RANGE;
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

            $value = trim($value);
            if (empty($value)) {
                return null;
            }

            $datePattern = '\d{4}-\d{2}-\d{2}';
            if (1 === preg_match('#^'.$datePattern.'$#', $value)) {
                $value .= 'T00:00:00Z';
            } elseif (1 === preg_match('#^'.$datePattern.'T\d{2}:\d{2}$#', $value)) {
                $value .= ':00Z';
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
            $date = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $value);
            if (false === $date) {
                return null;
            }

            return $date;
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

    public function normalizeBucket(array $bucket): ?array
    {
        $bucket['key'] = $bucket['key'] / 1000;

        return $bucket;
    }
}
