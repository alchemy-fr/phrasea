<?php

declare(strict_types=1);

namespace App\Doctrine;

use Alchemy\AclBundle\Entity\AccessControlEntry;
use Alchemy\AclBundle\Mapping\ObjectMapping;
use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use App\Entity\Publication;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Security\Core\Security;

class PublicationExtension implements QueryCollectionExtensionInterface
{
    private Security $security;
    private ObjectMapping $objectMapping;

    public function __construct(Security $security, ObjectMapping $objectMapping)
    {
        $this->security = $security;
        $this->objectMapping = $objectMapping;
    }

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        string $operationName = null
    ) {
        if (Publication::class !== $resourceClass) {
            return;
        }

        $user = $this->security->getUser();
        $groups = [];
        if ($user instanceof RemoteUser) {
            $groups = $user->getGroupIds();
        }
        $ownerId = $user instanceof RemoteUser ? $user->getId() : null;

        $rootAlias = $queryBuilder->getRootAliases()[0];

        if (
            !$this->security->isGranted('ROLE_ADMIN')
            && !$this->security->isGranted('ROLE_PUBLISH')
        ) {
            $queryBuilder->leftJoin($rootAlias.'.profile', 'p');

            $visibleConditions = implode(' AND ', [
                sprintf('(%1$s.config.publiclyListed = true OR (%1$s.config.publiclyListed IS NULL AND p.config.publiclyListed = true))', $rootAlias),
                sprintf('(%1$s.config.enabled = true OR (%1$s.config.enabled IS NULL AND p.config.enabled = true))', $rootAlias),
                $this->createDateClause($rootAlias, 'beginsAt', -1),
                $this->createDateClause($rootAlias, 'expiresAt', 1),
            ]);

            if (null !== $ownerId) {
                $aclConditions = [
                    sprintf('%s.ownerId = :uid', $rootAlias),
                    sprintf('ace.mask >= :edit_mask'),
                ];
                $queryBuilder->setParameter('uid', $ownerId);

                $aclUserGroupConditions = [
                    'ace.userType = :ut AND (ace.userId IS NULL OR ace.userId = :uid)',
                ];
                if (!empty($groups)) {
                    $aclUserGroupConditions[] = 'ace.userType = :gt AND ace.userId IN (:gids)';
                    $queryBuilder->setParameter('gt', AccessControlEntryInterface::TYPE_GROUP_VALUE);
                    $queryBuilder->setParameter('gids', $groups);
                }

                $queryBuilder->leftJoin(
                    AccessControlEntry::class,
                    'ace',
                    Join::WITH,
                    sprintf(
                        'ace.objectType = :ot AND (%s) AND (ace.objectId IS NULL OR ace.objectId = %s.id)',
                        implode(' OR ', $aclUserGroupConditions),
                        $rootAlias
                    )
                );
                $queryBuilder->setParameter('ut', AccessControlEntryInterface::TYPE_USER_VALUE);
                $queryBuilder->setParameter('ot', $this->objectMapping->getObjectKey(Publication::class));
                $queryBuilder->setParameter('ot', $this->objectMapping->getObjectKey(Publication::class));
                $queryBuilder->setParameter('edit_mask', PermissionInterface::EDIT);

                $queryBuilder->andWhere(sprintf('(%s) OR (%s)',
                    $visibleConditions,
                    implode(' OR ', $aclConditions)
                ));
            } else {
                $queryBuilder->andWhere($visibleConditions);
            }

            $queryBuilder->setParameter('now', date('Y-m-d H:i:s'));
        }
    }

    private function createDateClause(string $rootAlias, string $column, int $way): string
    {
        return sprintf(
            '((%1$s.config.%2$s IS NULL OR %1$s.config.%2$s %3$s :now) AND (%1$s.config.%2$s IS NOT NULL OR (p.config.%2$s IS NULL OR p.config.%2$s %3$s :now)))',
            $rootAlias,
            $column,
            $way > 0 ? '>=' : '<='
        );
    }
}
