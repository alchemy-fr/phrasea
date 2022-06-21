<?php

declare(strict_types=1);

namespace App\Api\DataProvider;

use Alchemy\AclBundle\Entity\AccessControlEntryRepository;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use ApiPlatform\Core\DataProvider\ContextAwareCollectionDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use App\Entity\Core\AttributeClass;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;

class AttributeClassCollectionDataProvider implements ContextAwareCollectionDataProviderInterface, RestrictedDataProviderInterface
{
    private EntityManagerInterface $em;
    private Security $security;

    public function __construct(EntityManagerInterface $em, Security $security)
    {
        $this->em = $em;
        $this->security = $security;
    }

    public function getCollection(string $resourceClass, string $operationName = null, array $context = [])
    {
        $user = $this->security->getUser();
        if (!$user instanceof RemoteUser) {
            return [];
        }

        $filters = $context['filters'] ?? [];

        $queryBuilder = $this->em->getRepository(AttributeClass::class)
            ->createQueryBuilder('t')
            ->innerJoin('t.workspace', 'w')
        ;

        if ($filters['workspaceId'] ?? false) {
            $queryBuilder
                ->andWhere('w.id = :ws')
                ->setParameter('ws', $filters['workspaceId']);
        }

        AccessControlEntryRepository::joinAcl(
            $queryBuilder,
            $user->getId(),
            $user->getGroupIds(),
            'workspace',
            'w',
            PermissionInterface::EDIT,
            false
        );
        $queryBuilder->andWhere('ace.id IS NOT NULL');

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return AttributeClass::class === $resourceClass;
    }
}
