<?php

declare(strict_types=1);

namespace App\Api\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class InWorkspacesFilter extends AbstractFilter
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
        if (empty($value)) {
            return;
        }
        if (!in_array($property, $this->getProperties(), true)) {
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
            ->andWhere(sprintf('o.workspace IN (:%s)', $parameterName))
            ->setParameter($parameterName, $value);
    }

    protected function getProperties(): ?array
    {
        return ['workspace'];
    }

    public function getDescription(string $resourceClass): array
    {
        $description = [];
        foreach ($this->getProperties() as $property) {
            $description[$property] = [
                'property' => $property,
                'type' => 'array',
                'required' => false,
                'description' => 'Filter by list of IDs',
                'schema' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'string',
                        'format' => 'uuid',
                    ],
                ],
            ];
        }

        return $description;
    }
}
