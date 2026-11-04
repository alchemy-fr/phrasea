<?php

declare(strict_types=1);

namespace App\Api\Filter;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\BackwardCompatibleFilterDescriptionTrait;
use ApiPlatform\Metadata\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\OpenApiParameterFilterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Parameter;
use ApiPlatform\OpenApi\Model\Parameter as OpenApiParameter;
use App\Elasticsearch\Filter\ElasticsearchFilterInterface;
use App\Elasticsearch\Filter\EsFieldTrait;
use App\Elasticsearch\Filter\SearchQuery;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use Elastica\Query;

/**
 * The "exact" strategy of the legacy SearchFilter, as a parameter filter.
 *
 * The value is a single value or a list (`?workspace[]=a&workspace[]=b`).
 * On a relation, each value is either the IRI or the ID of the related resource,
 * which the canonical ExactFilter (raw value) and IriFilter (IRI only) do not cover.
 *
 * API Platform leaves the `<key>[]` variants out of hydra:search: declare a
 * `<key>[]` QueryParameter without filter next to it to keep them documented.
 *
 * On Elasticsearch, the values become a terms query on the `es_field` of the parameter
 * (e.g. `workspaceId` for the `workspace` relation).
 */
final class ExactSearchFilter implements FilterInterface, OpenApiParameterFilterInterface, ElasticsearchFilterInterface
{
    use BackwardCompatibleFilterDescriptionTrait;
    use EsFieldTrait;

    public function __construct(
        private readonly IriConverterInterface $iriConverter,
    ) {
    }

    public function apply(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        $parameter = $context['parameter'];
        $property = $parameter->getProperty() ?? throw new InvalidArgumentException(sprintf('The filter parameter "%s" must specify a property.', $parameter->getKey()));

        $values = self::normalizeValues($parameter->getValue());
        if (empty($values)) {
            return;
        }

        $em = $queryBuilder->getEntityManager();
        $metadata = $em->getClassMetadata($resourceClass);
        if ($metadata->hasAssociation($property)) {
            $targetMetadata = $em->getClassMetadata($metadata->getAssociationTargetClass($property));
            $values = array_map(fn (string|int $value): string|int => $this->getIdFromValue($value, $targetMetadata), $values);
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $parameterName = $queryNameGenerator->generateParameterName($property);
        if (1 === \count($values)) {
            $queryBuilder
                ->andWhere(sprintf('%s.%s = :%s', $alias, $property, $parameterName))
                ->setParameter($parameterName, $values[0]);

            return;
        }

        $queryBuilder
            ->andWhere(sprintf('%s.%s IN (:%s)', $alias, $property, $parameterName))
            ->setParameter($parameterName, $values);
    }

    public function applyToElasticsearch(SearchQuery $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $parameter = $context['parameter'];
        $values = self::normalizeValues($parameter->getValue());
        if (empty($values)) {
            return;
        }

        $values = array_map(fn (string|int $value): string|int => $this->getIdFromValue($value), $values);

        $query->bool->addFilter(new Query\Terms(self::getEsField($parameter), array_map(strval(...), $values)));
    }

    /**
     * @return list<string|int> the scalar values of the list, ignoring operator maps (`key[gte]=…`)
     */
    private static function normalizeValues(mixed $value): array
    {
        return array_values(array_filter(
            (array) $value,
            static fn (mixed $value, int|string $key): bool => \is_int($key) && (\is_string($value) || \is_int($value)),
            ARRAY_FILTER_USE_BOTH,
        ));
    }

    private function getIdFromValue(string|int $value, ?ClassMetadata $targetMetadata = null): string|int
    {
        if (is_numeric($value)) {
            return $value;
        }

        try {
            $item = $this->iriConverter->getResourceFromIri($value, ['fetch_data' => false]);
        } catch (InvalidArgumentException) {
            // Not an IRI: the ID itself
            return $value;
        }

        if (null !== $targetMetadata) {
            $ids = $targetMetadata->getIdentifierValues($item);

            return (string) reset($ids);
        }

        if (method_exists($item, 'getId')) {
            return (string) $item->getId();
        }

        return $value;
    }

    public function getOpenApiParameters(Parameter $parameter): array
    {
        $schema = $parameter->getSchema() ?? ['type' => 'string'];
        $description = sprintf('Exact match on "%s" (ID or IRI for a relation)', $parameter->getProperty());

        return [
            new OpenApiParameter(name: $parameter->getKey(), in: 'query', description: $description, schema: $schema, explode: false),
            new OpenApiParameter(name: $parameter->getKey().'[]', in: 'query', description: $description.', any of the values', schema: [
                'type' => 'array',
                'items' => $schema,
            ], explode: true),
        ];
    }
}
