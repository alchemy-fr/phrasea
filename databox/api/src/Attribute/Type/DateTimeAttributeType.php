<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use App\Attribute\AttributeInterface;
use App\Elasticsearch\ESFacetInterface;
use App\Elasticsearch\SearchType;
use App\Util\DateUtil;

class DateTimeAttributeType extends AbstractAttributeType
{
    public const string NAME = 'date_time';

    public static function getName(): string
    {
        return static::NAME;
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

    public function convertToDbValue(mixed $value): ?string
    {
        if (!$value instanceof \DateTimeInterface) {
            $value = DateUtil::normalizeDate($value);
        }

        return $value?->format(\DateTimeInterface::ATOM);
    }

    /**
     * @return \DateTimeImmutable|null
     */
    public function denormalizeValue(?string $value): mixed
    {
        return DateUtil::normalizeDate($value);
    }

    public function getStringValue(?string $value, ?string $locale): string
    {
        $date = $this->denormalizeValue($value);
        if ($date) {
            if (AttributeInterface::NO_LOCALE === $locale) {
                $locale = 'en';
            }
            $formatter = new \IntlDateFormatter($locale, \IntlDateFormatter::LONG, \IntlDateFormatter::SHORT);

            return $formatter->format($date);
        }

        return '';
    }

    public function validate(mixed $value): ?array
    {
        if (null === DateUtil::normalizeDate($value)) {
            return ['Invalid date'];
        }

        return null;
    }

    public function normalizeElasticsearchValue(?string $value): mixed
    {
        if (empty($value)) {
            return null;
        }

        return $value;
    }
}
