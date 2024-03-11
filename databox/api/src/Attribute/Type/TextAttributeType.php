<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use App\Entity\Core\AttributeDefinition;
use App\Util\LocaleUtils;
use Elastica\Query;
use Elastica\Query\AbstractQuery;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class TextAttributeType extends AbstractAttributeType
{
    public const NAME = 'text';

    public static function getName(): string
    {
        return self::NAME;
    }

    public function getElasticSearchType(): string
    {
        return 'text';
    }

    public function createFilterQuery(string $field, $value): AbstractQuery
    {
        return new Query\Terms($field, $value);
    }

    public function getElasticSearchMapping(string $locale, AttributeDefinition $definition): array
    {
        $mapping = [];

        if (true
            // TODO Should always provision keyword?
            || $definition->isFacetEnabled()) {
            $mapping['fields'] = [
                'raw' => [
                    'type' => 'keyword',
                    'ignore_above' => 500,
                ],
            ];
        }

        $locales = [
            'ar' => 'arabic',
            'bg' => 'bulgarian',
            'bn' => 'bengali',
            'ca' => 'catalan',
            'ch' => 'cjk',
            'ckb' => 'sorani',
            'cs' => 'czech',
            'da' => 'danish',
            'de' => 'german',
            'el' => 'greek',
            'en' => 'english',
            'es' => 'spanish',
            'et' => 'estonian',
            'eu' => 'basque',
            'fa' => 'persian',
            'fi' => 'finnish',
            'fr' => 'french',
            'ga' => 'irish',
            'gl' => 'galician',
            'hi' => 'hindi',
            'hu' => 'hungarian',
            'hy' => 'armenian',
            'id' => 'indonesian',
            'it' => 'italian',
            'ja' => 'cjk',
            'ko' => 'cjk',
            'lt' => 'lithuanian',
            'lv' => 'latvian',
            'nl' => 'dutch',
            'no' => 'norwegian',
            'pt' => 'portuguese',
            'pt_BR' => 'brazilian',
            'ro' => 'romanian',
            'ru' => 'russian',
            'sv' => 'swedish',
            'th' => 'thai',
            'tr' => 'turkish',
        ];

        $language = LocaleUtils::extractLanguageFromLocale($locale);
        if (isset($locales[$locale])) {
            $mapping['analyzer'] = $locales[$locale];
        } elseif (isset($locales[$language])) {
            $mapping['analyzer'] = $locales[$language];
        } else {
            $mapping['analyzer'] = 'text';
        }

        return $mapping;
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
