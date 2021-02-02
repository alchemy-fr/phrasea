<?php

declare(strict_types=1);

namespace App\Api\DataProvider;

use ApiPlatform\Core\DataProvider\ContextAwareCollectionDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use App\Elasticsearch\CollectionSearch;
use App\Entity\Core\Collection;
use App\Entity\Core\Workspace;
use Doctrine\ORM\EntityManagerInterface;

class WorkspaceCollectionDataProvider implements ContextAwareCollectionDataProviderInterface, RestrictedDataProviderInterface
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function getCollection(string $resourceClass, string $operationName = null, array $context = [])
    {
        return $this->em->getRepository(Workspace::class)
            ->findAll();
        // TODO filter for user $context['userId'], $context['groupIds'], $context['filters'] ?? []
        //$this->em->search($context['userId'], $context['groupIds'], $context['filters'] ?? []);
    }

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return Workspace::class === $resourceClass;
    }

}
