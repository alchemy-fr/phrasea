<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use App\Elasticsearch\SearchType;
use App\Entity\Core\AttributeDefinition;
use Elastica\Query;
use Elastica\Query\AbstractQuery;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class NumberAttributeType extends AbstractAttributeType
{
    final public const NAME = 'number';

    public static function getName(): string
    {
        return self::NAME;
    }

    public function getElasticSearchMapping(string $locale): ?array
    {
        return [
            'fields' => [
                'raw' => [
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

    public function createFilterQuery(string $field, $value): AbstractQuery
    {
        return new Query\Terms($field, $value);
    }
}
