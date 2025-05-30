<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use App\Elasticsearch\SearchType;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class NumberAttributeType extends AbstractAttributeType
{
    final public const string NAME = 'number';

    public static function getName(): string
    {
        return self::NAME;
    }

    public function getElasticSearchMapping(string $locale): ?array
    {
        return [
            'fields' => [
                AttributeTypeInterface::RAW_PROP => [
                    'type' => 'keyword',
                ],
            ],
        ];
    }

    public function getElasticSearchSearchType(): ?SearchType
    {
        return SearchType::Match;
    }

    public function getElasticSearchType(): string
    {
        return 'long';
    }

    public function supportsSuggest(): bool
    {
        return true;
    }

    /**
     * @param int|float|string $value
     *
     * @return float
     */
    public function normalizeElasticsearchValue($value)
    {
        return (float) $value;
    }

    public function validate($value, ExecutionContextInterface $context): void
    {
        if (null === $value) {
            return;
        }

        if (!is_numeric($value)) {
            $context->addViolation('Invalid number');
        }
    }

    public function supportsAggregation(): bool
    {
        return true;
    }
}
