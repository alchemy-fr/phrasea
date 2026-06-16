<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use Alchemy\CoreBundle\Util\LocaleUtil;
use App\Attribute\AttributeLocaleInterface;
use App\Elasticsearch\SearchType;

class TextAttributeType extends AbstractAttributeType
{
    public const string NAME = 'text';

    public static function getName(): string
    {
        return static::NAME;
    }

    public function getElasticSearchType(): string
    {
        return 'text';
    }

    public function getElasticSearchSearchType(): ?SearchType
    {
        return SearchType::Match;
    }

    public function convertToDbValue(mixed $value): ?string
    {
        $value = parent::convertToDbValue($value);
        if (null === $value) {
            return null;
        }

        return trim($value);
    }

    public function supportsElasticSearchFuzziness(): bool
    {
        return true;
    }

    public function getElasticSearchMapping(string $locale): ?array
    {
        $mapping = [
            'fields' => [
                AttributeTypeInterface::RAW_PROP => [
                    'type' => 'keyword',
                    'ignore_above' => 256,
                ],
            ],
        ];

        $language = LocaleUtil::extractLanguageFromLocale($locale);
        if (isset(AttributeLocaleInterface::LOCALES[$locale])) {
            $mapping['analyzer'] = AttributeLocaleInterface::LOCALES[$locale];
        } elseif (isset(AttributeLocaleInterface::LOCALES[$language])) {
            $mapping['analyzer'] = AttributeLocaleInterface::LOCALES[$language];
        } else {
            $mapping['analyzer'] = 'text';
        }

        return $mapping;
    }

    public function isMappingLocaleAware(): bool
    {
        return $this->isLocaleAware();
    }

    public function isLocaleAware(): bool
    {
        return true;
    }

    public function supportsSuggest(): bool
    {
        return true;
    }

    public function validate(mixed $value): ?array
    {
        if (!is_string($value) && !(is_object($value) && method_exists($value, '__toString'))) {
            return ['Invalid value'];
        }

        return null;
    }

    public function getAggregationField(): ?string
    {
        return AttributeTypeInterface::RAW_PROP;
    }

    public function supportsAggregation(): bool
    {
        return true;
    }

    public function getElasticSearchRawField(): ?string
    {
        return AttributeTypeInterface::RAW_PROP;
    }

    public function getElasticSearchSortSubField(): ?string
    {
        return AttributeTypeInterface::RAW_PROP;
    }
}
