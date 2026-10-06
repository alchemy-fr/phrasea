<?php

declare(strict_types=1);

namespace App\Api\Filter;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\BackwardCompatibleFilterDescriptionTrait;
use ApiPlatform\Metadata\OpenApiParameterFilterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Parameter;
use ApiPlatform\OpenApi\Model\Parameter as OpenApiParameter;
use Doctrine\ORM\QueryBuilder;

final class AssetTypeTargetFilter implements FilterInterface, OpenApiParameterFilterInterface
{
    use BackwardCompatibleFilterDescriptionTrait;

    public function apply(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        $parameter = $context['parameter'];
        $property = $parameter->getProperty() ?? 'target';
        $value = $parameter->getValue();
        if (!$value) {
            return;
        }

        $parameterName = $queryNameGenerator->generateParameterName($property);
        $queryBuilder
            ->andWhere(sprintf('BIT_AND(%s.%s, :%s) != 0', $queryBuilder->getRootAliases()[0], $property, $parameterName))
            ->setParameter($parameterName, (int) $value);
    }

    public function getOpenApiParameters(Parameter $parameter): OpenApiParameter
    {
        return new OpenApiParameter(
            name: $parameter->getKey(),
            in: 'query',
            description: 'Filter by asset type (bitmask)',
            schema: [
                'type' => 'integer',
            ],
            explode: false,
        );
    }
}
