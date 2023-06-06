<?php

declare(strict_types=1);

namespace App\Api\DataProvider;

use ApiPlatform\Core\DataProvider\ContextAwareCollectionDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use App\Entity\Core\TagFilterRule;
use Doctrine\ORM\EntityManagerInterface;

class TagFilterRuleCollectionDataProvider implements ContextAwareCollectionDataProviderInterface, RestrictedDataProviderInterface
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function getCollection(string $resourceClass, string $operationName = null, array $context = [])
    {
        $criteria = [];
        $filters = $context['filters'] ?? [];
        if (isset($filters['collectionId'])) {
            $criteria['objectType'] = TagFilterRule::TYPE_COLLECTION;
            $criteria['objectId'] = $filters['collectionId'];
        }
        if (isset($filters['workspaceId'])) {
            $criteria['objectType'] = TagFilterRule::TYPE_WORKSPACE;
            $criteria['objectId'] = $filters['workspaceId'];
        }

        return $this->em->getRepository(TagFilterRule::class)->findBy($criteria);
    }

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return TagFilterRule::class === $resourceClass;
    }
}
