<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use App\Elasticsearch\ESFacetInterface;
use App\Elasticsearch\SearchType;
use App\Util\DateUtil;
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
                AttributeTypeInterface::RAW_PROP => [
                    'type' => 'keyword',
                ],
            ],
        ];
    }

    public function getElasticSearchRawField(): ?string
    {
        return AttributeTypeInterface::RAW_PROP;
    }

    public function getElasticSearchTextSubField(): ?string
    {
        return 'text';
    }

    public function normalizeValue($value): ?string
    {
        if (!$value instanceof \DateTimeInterface) {
            $value = DateUtil::normalizeDate($value);
        }

        return $value?->format(\DateTimeInterface::ATOM);
    }

    /**
     * @return \DateTimeImmutable|null
     */
    public function denormalizeValue(?string $value)
    {
        return DateUtil::normalizeDate($value);
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
