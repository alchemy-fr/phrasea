<?php

declare(strict_types=1);

namespace App\Api\Extension;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Core\AttributeEntity;
use App\Entity\Core\EntityList;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

class AttributeEntityExtension implements QueryCollectionExtensionInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if (AttributeEntity::class !== $resourceClass) {
            return;
        }

        $listId = $context['filters']['list'] ?? null;
        if (isset($listId)) {
            $list = DoctrineUtil::findStrict($this->em, EntityList::class, $listId);
            if ($this->isGranted(AbstractVoter::EDIT, $list)) {
                return;
            }
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];

        $userIdentifier = $this->getUserOrOAuthClient()?->getUserIdentifier();

        if ($userIdentifier) {
            $queryBuilder
                ->andWhere(sprintf('%1$s.status = :approved OR %1$s.creatorId = :uid', $rootAlias))
                ->setParameter('approved', AttributeEntity::STATUS_APPROVED)
                ->setParameter('uid', $userIdentifier)
            ;
        } else {
            $queryBuilder
                ->andWhere(sprintf('%1$s.status = :approved', $rootAlias))
                ->setParameter('approved', AttributeEntity::STATUS_APPROVED)
            ;
        }
    }
}
