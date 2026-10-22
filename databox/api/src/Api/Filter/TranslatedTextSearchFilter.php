<?php

declare(strict_types=1);

namespace App\Api\Filter;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\BackwardCompatibleFilterDescriptionTrait;
use ApiPlatform\Metadata\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\OpenApiParameterFilterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Parameter;
use ApiPlatform\OpenApi\Model\Parameter as OpenApiParameter;
use Doctrine\ORM\QueryBuilder;

/**
 * Case-insensitive "contains" match (`LIKE %value%`) on the properties of the parameter, or on any
 * translation of a translated field (all locales, see TranslationsTrait).
 *
 * The resource must use TranslationsTrait. Not accent-insensitive.
 */
final class TranslatedTextSearchFilter implements FilterInterface, OpenApiParameterFilterInterface
{
    use BackwardCompatibleFilterDescriptionTrait;

    /**
     * @param string $translationField the translated field whose translations are searched
     *                                 (a key of the `translations` column)
     */
    public function __construct(
        private readonly string $translationField = 'name',
    ) {
    }

    public function apply(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        /** @var Parameter $parameter */
        $parameter = $context['parameter'];
        $value = $parameter->getValue();
        if (!\is_scalar($value) || '' === trim((string) $value)) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $parameterName = $queryNameGenerator->generateParameterName($parameter->getKey());
        $queryBuilder->setParameter($parameterName, '%'.addcslashes((string) $value, '\\%_').'%');

        $fields = array_map(
            static fn (string $property): string => $alias.'.'.$property,
            $this->getProperties($parameter),
        );
        $fields[] = sprintf("TRANSLATION_VALUES(%s.translations, '%s')", $alias, $this->translationField);

        $queryBuilder->{$context['whereClause'] ?? 'andWhere'}($queryBuilder->expr()->orX(...array_map(
            static fn (string $field): string => sprintf('LOWER(%s) LIKE LOWER(:%s) ESCAPE \'\\\'', $field, $parameterName),
            $fields,
        )));
    }

    public function getOpenApiParameters(Parameter $parameter): OpenApiParameter
    {
        return new OpenApiParameter(
            name: $parameter->getKey(),
            in: 'query',
            description: $parameter->getDescription() ?? sprintf(
                'Contains match on %s or any translation of %s',
                implode(', ', $this->getProperties($parameter)),
                $this->translationField,
            ),
            schema: $parameter->getSchema() ?? ['type' => 'string'],
        );
    }

    /**
     * @return list<string>
     */
    private function getProperties(Parameter $parameter): array
    {
        $properties = $parameter->getProperties() ?? array_filter([$parameter->getProperty()]);
        if (empty($properties)) {
            throw new InvalidArgumentException(sprintf('The filter parameter with key "%s" must specify its properties.', $parameter->getKey()));
        }

        return array_values($properties);
    }
}
