<?php

declare(strict_types=1);

namespace App\Api\DataProvider;

use ApiPlatform\Core\DataProvider\ContextAwareCollectionDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use App\Entity\Core\RenditionDefinition;
use Doctrine\ORM\EntityManagerInterface;

class RenditionDefinitionCollectionDataProvider implements ContextAwareCollectionDataProviderInterface, RestrictedDataProviderInterface
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function getCollection(string $resourceClass, string $operationName = null, array $context = [])
    {

        $queryBuilder = $this->em->getRepository(RenditionDefinition::class)
            ->createQueryBuilder('t');

        $filters = $context['filters'] ?? [];
        if (isset($filters['workspaceIds'])) {
            $queryBuilder->andWhere('t.workspace IN (:wids)')
                ->setParameter('wids', $filters['workspaceIds']);
        }

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return RenditionDefinition::class === $resourceClass;
    }
}
