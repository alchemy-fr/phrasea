<?php

declare(strict_types=1);

namespace App\Api\DataProvider;

use Alchemy\AclBundle\Entity\AccessControlEntryRepository;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use ApiPlatform\Core\DataProvider\ContextAwareCollectionDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use App\Entity\Core\AttributeDefinition;

class AttributeDefinitionCollectionDataProvider extends AbstractSecurityDataProvider implements ContextAwareCollectionDataProviderInterface, RestrictedDataProviderInterface
{
    public function getCollection(string $resourceClass, string $operationName = null, array $context = [])
    {
        $filters = $context['filters'] ?? [];

        $queryBuilder = $this->em->getRepository(AttributeDefinition::class)
            ->createQueryBuilder('t')
            ->innerJoin('t.class', 'ac')
        ;

        if ($filters['workspaceId'] ?? false) {
            $queryBuilder
                ->andWhere('t.workspace = :ws')
                ->setParameter('ws', $filters['workspaceId']);
        }

        if (!$this->isChuckNorris()) {
            $user = $this->security->getUser();

            if ($user instanceof RemoteUser) {
                AccessControlEntryRepository::joinAcl(
                    $queryBuilder,
                    $user->getId(),
                    $user->getGroupIds(),
                    'attribute_class',
                    'ac',
                    PermissionInterface::VIEW,
                    false
                );
                $queryBuilder->andWhere('ac.public = true OR ace.id IS NOT NULL');
            } else {
                $queryBuilder->andWhere('ac.public = true');
            }
        }

        return $queryBuilder
            ->addOrderBy('t.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return AttributeDefinition::class === $resourceClass;
    }
}
