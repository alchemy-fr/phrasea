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

        return parent::getGroupValueLabel($value);
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

            foreach ([
                         ['p'=>'#^(\d{4})\D(\d{2})\D(\d{2})$#', 'f'=>'%04d-%02d-%02dT00:00:00Z', 'm'=>[1, 2, 3]],
                         ['p'=>'#^(\d{4})\D(\d{2})\D(\d{2})\D(\d{2})\D(\d{2})\D(\d{2})$#', 'f'=>'%04d-%02d-%02dT%02d:%02d:%02dZ', 'm'=>[1, 2, 3, 4, 5, 6]],
                         ['p'=>'#^(\d{4})\D(\d{2})\D(\d{2})T(\d{2})\D(\d{2})$#', 'f'=>'%04d-%02d-%02dT%02d:%02d:00Z', 'r'=>[1, 2, 3, 4, 5]],
                     ] as $tryout) {
                $matches = [];
                if(preg_match($tryout['p'], $value, $matches) === 1) {
                    // m is the mapping from matches[x] to arg[i] for vsprintf(f, args)
                    $args = array_map(function($a) use($matches) {return (int)($matches[$a]);}, $tryout['m']);
                    $value = vsprintf($tryout['f'], $args);
                    break;
                }
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
