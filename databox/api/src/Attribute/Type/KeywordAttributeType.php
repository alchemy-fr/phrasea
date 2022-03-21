<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use App\Elasticsearch\Mapping\IndexMappingUpdater;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Throwable;

class KeywordAttributeType extends AbstractAttributeType
{
    public const NAME = 'keyword';

    public static function getName(): string
    {
        return self::NAME;
    }

    public function getElasticSearchType(): string
    {
        return 'keyword';
    }

    public function getElasticSearchMapping(string $language): array
    {
        $mapping = [];
        if (IndexMappingUpdater::NO_LOCALE !== $language) {
            $mapping['analyzer'] = 'keyword_'.$language;
        }

        return $mapping;
    }

    public function normalizeValue($value)
    {
        if (null === $value) {
            return null;
        }

        try {
            return (string)$value;
        } catch (Throwable $e) {
            return null;
        }
    }

    public function isLocaleAware(): bool
    {
        return true;
    }

    public function validate($value, ExecutionContextInterface $context): void
    {
        if (null === $value) {
            return;
        }

        if (!is_string($value) && !(is_object($value) && method_exists($value , '__toString'))) {
            $context->addViolation('Invalid text value');
        }
    }

    public function supportsAggregation(): bool
    {
        return true;
    }
}
