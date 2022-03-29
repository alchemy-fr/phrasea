<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use App\Elasticsearch\Mapping\IndexMappingUpdater;
use App\Entity\Core\AttributeDefinition;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Throwable;

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

    public function getElasticSearchMapping(string $language, AttributeDefinition $definition): array
    {
        $mapping = [];

        if (true
            // TODO Should always provision keyword?
            || $definition->isFacetEnabled()) {
            $mapping['fields'] = [
                'raw' => [
                    'type' => 'keyword',
                ],
            ];
        }

        if (IndexMappingUpdater::NO_LOCALE !== $language) {
            $mapping['analyzer'] = 'text_'.$language;
        } else {
            $mapping['analyzer'] = 'text';
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
