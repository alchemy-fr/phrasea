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
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Filters by a list of workspace IDs, given as a list or as a comma-separated string.
 */
final class InWorkspacesFilter implements FilterInterface, OpenApiParameterFilterInterface
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
        $property = $parameter->getProperty() ?? 'workspace';
        $value = $parameter->getValue();
        if (empty($value)) {
            return;
        }

        if (is_string($value)) {
            $value = explode(',', trim($value));
        }

        foreach ($value as $id) {
            if (!Uuid::isValid($id)) {
                throw new BadRequestHttpException(sprintf('Invalid ID: "%s"', $id));
            }
        }

        $parameterName = $queryNameGenerator->generateParameterName($property);
        $queryBuilder
            ->andWhere(sprintf('%s.%s IN (:%s)', $queryBuilder->getRootAliases()[0], $property, $parameterName))
            ->setParameter($parameterName, $value);
    }

    public function getOpenApiParameters(Parameter $parameter): OpenApiParameter
    {
        return new OpenApiParameter(
            name: $parameter->getKey(),
            in: 'query',
            description: 'Filter by list of IDs',
            schema: [
                'type' => 'array',
                'items' => [
                    'type' => 'string',
                    'format' => 'uuid',
                ],
            ],
            style: 'deepObject',
        );
    }
}
