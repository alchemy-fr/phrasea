<?php

declare(strict_types=1);

namespace App\Doctrine;

use App\Entity\Asset;
use Doctrine\ORM\QueryBuilder;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;

#[AutoconfigureTag(
    name: 'api_platform.doctrine.orm.query_extension.collection',
    attributes: ['priority' => -20]
)]
class AssetsExtension implements QueryCollectionExtensionInterface
{
    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if (Asset::class !== $resourceClass || !($context['operation'] instanceof CollectionOperationInterface)) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $queryBuilder->addOrderBy(sprintf('%s.position', $rootAlias), 'ASC');
        $queryBuilder->addOrderBy(sprintf('%s.createdAt', $rootAlias), 'ASC');
    }
}
