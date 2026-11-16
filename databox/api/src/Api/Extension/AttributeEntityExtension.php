<?php

declare(strict_types=1);

namespace App\Api\Extension;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Api\Traits\ParameterValuesTrait;
use App\Entity\Core\AttributeEntity;
use App\Entity\Core\EntityList;
use App\Entity\Core\Workspace;
use App\Security\Voter\AssetContainerVoterInterface;
use App\Security\Voter\EntityListVoter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

class AttributeEntityExtension implements QueryCollectionExtensionInterface
{
    use ParameterValuesTrait;
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

        $rootAlias = $queryBuilder->getRootAliases()[0];

        // The editors of the requested list(s) also see the pending values
        $editableListIds = $this->getEditableListIds(null !== $operation ? self::getParameterValue($operation, 'list') : null);
        if (null !== $editableListIds) {
            // Restricted on the checked IDs themselves: the "list" filter skips the values with a non-integer key (`?list[x]=`)
            $parameterName = $queryNameGenerator->generateParameterName('editableLists');
            $queryBuilder
                ->andWhere(sprintf('%s.list IN (:%s)', $rootAlias, $parameterName))
                ->setParameter($parameterName, $editableListIds);

            return;
        }

        $userIdentifier = $this->getUserOrOAuthClient()?->getUserIdentifier();

        if ($userIdentifier) {
            // Whoever may set attribute values in a workspace may pick its pending values too
            $editableWorkspaces = [];
            $workspaceIds = null !== $operation ? (array) self::getParameterValue($operation, 'workspace', []) : [];
            foreach (array_unique(array_filter(array_map(static fn (mixed $id): string => basename((string) $id), $workspaceIds))) as $workspaceId) {
                $workspace = $this->em->find(Workspace::class, $workspaceId);
                if ($workspace instanceof Workspace && $this->isGranted(AssetContainerVoterInterface::ASSET_EDIT_ATTRIBUTES, $workspace)) {
                    $editableWorkspaces[] = $workspaceId;
                }
            }

            $visible = sprintf('%1$s.status = :approved OR %1$s.creatorId = :uid', $rootAlias);
            if (!empty($editableWorkspaces)) {
                $visible .= sprintf(' OR (%1$s.status = :pending AND %1$s.workspace IN (:editableWorkspaces))', $rootAlias);
                $queryBuilder
                    ->setParameter('pending', AttributeEntity::STATUS_PENDING)
                    ->setParameter('editableWorkspaces', $editableWorkspaces);
            }
            $queryBuilder
                ->andWhere($visible)
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

    /**
     * The "list" parameter: an ID or IRI, or a list of them, whatever their keys.
     *
     * @return string[]|null the IDs of the lists, null unless every one of them is given as a string and has editable values
     */
    private function getEditableListIds(mixed $filter): ?array
    {
        $values = \is_array($filter) ? array_values($filter) : [$filter];
        if ([] === $values) {
            return null;
        }
        foreach ($values as $value) {
            if (!\is_string($value) || '' === $value) {
                return null;
            }
        }

        $listIds = [];
        $editsEveryList = true;
        foreach ($values as $value) {
            $list = DoctrineUtil::findStrict($this->em, EntityList::class, basename($value), throw404: true);
            $editsEveryList = $editsEveryList && $this->isGranted(EntityListVoter::EDIT_VALUES, $list);
            $listIds[] = $list->getId();
        }

        return $editsEveryList ? array_values(array_unique($listIds)) : null;
    }
}
