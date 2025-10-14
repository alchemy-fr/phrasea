<?php

declare(strict_types=1);

namespace App\Api\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

final class SearchFilter extends AbstractFilter
{
    protected function filterProperty(
        string $property,
        $value,
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if (
            null === $value
            || !$this->isPropertyEnabled($property, $resourceClass)
            || !$this->isPropertyMapped($property, $resourceClass, true)
        ) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];

        $parameterName = $queryNameGenerator->generateParameterName($property);
        $queryBuilder
            ->andWhere(sprintf('%s.%s = :%s', $alias, $property, $parameterName))
            ->setParameter($parameterName, (string) $value);
    }

    protected function getProperties(): ?array
    {
        return [':property'];
    }

    public function getDescription(string $resourceClass): array
    {
        $description = [];
        foreach ($this->getProperties() as $property) {
            $description[$property] = [
                'property' => $property,
                'type' => 'string',
                'required' => false,
                'description' => 'Filter by value',
                'schema' => [
                    'type' => 'string',
                ],
            ];
        }

        return $description;
    }
}
