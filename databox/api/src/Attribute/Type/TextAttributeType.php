<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use Alchemy\CoreBundle\Util\LocaleUtil;
use App\Attribute\AttributeLocaleInterface;
use App\Elasticsearch\SearchType;
use Elastica\Query;
use Elastica\Query\AbstractQuery;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class TextAttributeType extends AbstractAttributeType
{
    public const string NAME = 'text';

    public static function getName(): string
    {
        return self::NAME;
    }

    public function getElasticSearchType(): string
    {
        return 'text';
    }

    public function getElasticSearchSearchType(): ?SearchType
    {
        return SearchType::Match;
    }

    public function supportsElasticSearchFuzziness(): bool
    {
        return true;
    }

    public function createFilterQuery(string $field, $value): AbstractQuery
    {
        return new Query\Terms($field, $value);
    }

    public function getElasticSearchMapping(string $locale): ?array
    {
        $mapping = [
            'fields' => [
                'raw' => [
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

    public function validate($value, ExecutionContextInterface $context): void
    {
        if (null === $value) {
            return;
        }

        if (!is_string($value) && !(is_object($value) && method_exists($value, '__toString'))) {
            $context->addViolation('Invalid text value');
        }
    }

    public function getAggregationField(): ?string
    {
        return 'raw';
    }

    public function supportsAggregation(): bool
    {
        return true;
    }
}
