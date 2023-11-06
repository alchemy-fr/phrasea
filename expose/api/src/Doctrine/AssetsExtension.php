<?php

declare(strict_types=1);

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Asset;
use Doctrine\ORM\QueryBuilder;

class AssetsExtension implements QueryCollectionExtensionInterface
{
    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        Operation $operation = null,
        array $context = []
    ): void {
        if (Asset::class !== $resourceClass || !($context['operation'] instanceof CollectionOperationInterface)) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $queryBuilder->addOrderBy(sprintf('%s.position', $rootAlias), 'ASC');
        $queryBuilder->addOrderBy(sprintf('%s.createdAt', $rootAlias), 'ASC');
    }
}
