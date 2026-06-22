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

    #[\Override]
    public function supportsAggregation(): bool
    {
        return true;
    }

    #[\Override]
    public function supportsSuggest(): bool
    {
        return false;
    }

    public function getElasticSearchSearchType(): ?SearchType
    {
        return SearchType::Match;
    }

    #[\Override]
    public function getGroupValueLabel($value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }

        return parent::getGroupValueLabel($value);
    }

    #[\Override]
    public function getFacetType(): string
    {
        return ESFacetInterface::TYPE_DATE_RANGE;
    }

    public function getElasticSearchType(): string
    {
        return 'date';
    }

    #[\Override]
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

    #[\Override]
    public function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value;
        }

        $date = DateUtil::normalizeDate($value);
        if ($date instanceof \DateTimeInterface) {
            return $date;
        }

        return parent::normalizeValue($value);
    }

    #[\Override]
    public function convertToDbValue(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }

        return parent::convertToDbValue($value);
    }

    /**
     * @return \DateTimeImmutable|null
     */
    #[\Override]
    public function denormalizeValue(?string $value): mixed
    {
        return DateUtil::normalizeDate($value);
    }

    #[\Override]
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

    #[\Override]
    public function normalizeElasticsearchValue(?string $value): mixed
    {
        if (empty($value) || null === DateUtil::normalizeDate($value)) {
            return null;
        }

        return $value;
    }
}
