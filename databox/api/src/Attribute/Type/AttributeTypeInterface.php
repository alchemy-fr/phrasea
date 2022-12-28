<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use App\Entity\Core\AttributeDefinition;
use Elastica\Query\AbstractQuery;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

interface AttributeTypeInterface
{
    public static function getName(): string;

    public function getElasticSearchType(): string;

    public function getFacetType(): string;

    public function createFilterQuery(string $field, $value): AbstractQuery;

    public function supportsAggregation(): bool;

    public function getElasticSearchMapping(string $locale, AttributeDefinition $definition): array;

    /**
     * Normalize value for database.
     *
     * @param mixed $value
     */
    public function normalizeValue($value): ?string;

    /**
     * De-normalize value from database to PHP.
     *
     * @return mixed
     */
    public function denormalizeValue(?string $value);

    /**
     * Format value for client.
     *
     * @param mixed $value
     */
    public function getGroupValueLabel($value): ?string;

    /**
     * De-normalize value from Elasticsearch to database.
     *
     * @param mixed $value
     */
    public function denormalizeElasticsearchValue($value): ?string;

    /**
     * Normalize value from database to ES index.
     *
     * @return mixed
     */
    public function normalizeElasticsearchValue(?string $value);

    public function isLocaleAware(): bool;

    public function validate($value, ExecutionContextInterface $context): void;

    public function getAggregationField(): ?string;
}
