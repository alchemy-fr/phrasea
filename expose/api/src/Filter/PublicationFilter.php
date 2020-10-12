<?php

declare(strict_types=1);

namespace App\Filter;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\AbstractContextAwareFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Core\Exception\InvalidArgumentException;
use Doctrine\ORM\QueryBuilder;

class PublicationFilter extends AbstractContextAwareFilter
{
    protected function filterProperty(
        string $property,
        $value,
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        string $operationName = null
    ) {
    }

    public function apply(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        string $operationName = null,
        array $context = []
    ) {
        parent::apply($queryBuilder, $queryNameGenerator, $resourceClass, $operationName, $context);

        if (!(isset($context['filters']['flatten']) && true === $this->normalizeBoolValue($context['filters']['flatten'], 'flatten'))) {
            $queryBuilder->andWhere(sprintf('o.parent IS NULL'));
        }
    }

    public function getDescription(string $resourceClass): array
    {
        if (!$this->properties) {
            return [];
        }

        $description = [];
        foreach ($this->properties as $property => $strategy) {
            $description[$property] = [
                'property' => $property,
                'type' => 'bool',
                'required' => false,
                'swagger' => [
                    'description' => 'Get all the publications, regardless the hierarchy',
                    'type' => 'boolean',
                ],
            ];
        }

        return $description;
    }

    private function normalizeBoolValue($value, string $property): ?bool
    {
        if (in_array($value, [true, 'true', '1'], true)) {
            return true;
        }

        if (in_array($value, [false, 'false', '0'], true)) {
            return false;
        }

        $this->getLogger()->notice('Invalid filter ignored', [
            'exception' => new InvalidArgumentException(sprintf('Invalid boolean value for "%s" property, expected one of ( "%s" )', $property, implode('" | "', [
                'true',
                'false',
                '1',
                '0',
            ]))),
        ]);

        return null;
    }
}
