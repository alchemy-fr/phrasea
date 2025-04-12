<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use App\Elasticsearch\ESFacetInterface;
use App\Elasticsearch\SearchType;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

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

    public function supportsSuggest(): bool
    {
        return false;
    }

    public function getElasticSearchSearchType(): ?SearchType
    {
        return SearchType::Match;
    }

    public function getGroupValueLabel($value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }

        return parent::getGroupValueLabel($value);
    }

    public function getFacetType(): string
    {
        return ESFacetInterface::TYPE_DATE_RANGE;
    }

    public function getElasticSearchType(): string
    {
        return 'date';
    }

    public function getElasticSearchMapping(string $locale): ?array
    {
        return [
            'fields' => [
                'text' => [
                    'type' => 'text',
                ],
            ],
        ];
    }

    public function getElasticSearchTextSubField(): ?string
    {
        return 'text';
    }

    public function normalizeValue($value): ?string
    {
        if (!$value instanceof \DateTimeInterface) {
            if (empty($value)) {
                return null;
            }

            $value = trim((string) $value);
            if (empty($value)) {
                return null;
            }

            foreach ([
                [
                    'p' => '#^(\d{4})\D(\d{2})\D(\d{2})$#',
                    'f' => '%04d-%02d-%02dT00:00:00Z',
                    'm' => [1, 2, 3]],
                [
                    'p' => '#^(\d{4})\D(\d{2})\D(\d{2})\D(\d{2})\D(\d{2})\D(\d{2})$#',
                    'f' => '%04d-%02d-%02dT%02d:%02d:%02dZ',
                    'm' => [1, 2, 3, 4, 5, 6]],
                [
                    'p' => '#^(\d{4})\D(\d{2})\D(\d{2})T(\d{2})\D(\d{2})$#',
                    'f' => '%04d-%02d-%02dT%02d:%02d:00Z',
                    'm' => [1, 2, 3, 4, 5]],
            ] as $tryout) {
                $matches = [];
                if (1 === preg_match($tryout['p'], $value, $matches)) {
                    // m is the mapping from matches[x] to arg[i] for vsprintf(f, args)
                    $args = array_map(fn ($a) => (int) $matches[$a], $tryout['m']);
                    $value = vsprintf($tryout['f'], $args);
                    break;
                }
            }
            if (false === $value = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $value)) {
                return null;
            }
        }

        return $value->format(\DateTimeInterface::ATOM);
    }

    /**
     * @return \DateTimeImmutable|null
     */
    public function denormalizeValue(?string $value)
    {
        if (null === $value) {
            return null;
        }

        try {
            $date = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $value);
            if (false === $date) {
                return null;
            }

            return $date;
        } catch (\Throwable) {
            return null;
        }
    }

    public function validate($value, ExecutionContextInterface $context): void
    {
        if (empty($value)) {
            return;
        }

        try {
            new \DateTimeImmutable($value);
        } catch (\Exception) {
            $context->addViolation('Invalid date');

            return;
        }
    }

    public function normalizeBucket(array $bucket): ?array
    {
        $bucket['key'] /= 1000;

        return $bucket;
    }
}
