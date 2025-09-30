<?php

namespace App\Api\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

final class AssetTypeTargetFilter extends AbstractFilter
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
        if (null === $value) {
            return;
        }

        if (!in_array($property, $this->getProperties(), true)) {
            return;
        }

        $parameterName = $queryNameGenerator->generateParameterName($property);
        $queryBuilder
            ->andWhere(sprintf('BIT_AND(o.%s, :%s) != 0', $property, $parameterName))
            ->setParameter($parameterName, (int) $value);
    }

    protected function getProperties(): ?array
    {
        return ['target'];
    }

    public function getDescription(string $resourceClass): array
    {
        $description = [];
        foreach ($this->getProperties() as $property) {
            $description[$property] = [
                'property' => $property,
                'type' => 'int',
                'required' => false,
                'description' => 'Filter by asset type (bitmask)',
                'schema' => [
                    'type' => 'integer',
                ],
            ];
        }

        return $description;
    }
}
