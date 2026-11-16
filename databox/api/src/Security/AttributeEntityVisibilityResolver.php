<?php

declare(strict_types=1);

namespace App\Security;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\Metadata\Operation;
use App\Api\Traits\ParameterValuesTrait;
use App\Entity\Core\EntityList;
use App\Entity\Core\Workspace;
use App\Security\Voter\AssetContainerVoterInterface;
use App\Security\Voter\EntityListVoter;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Resolves the visibility of the entity list values for the current user and the "list" and "workspace"
 * parameters of the collection operation, the same for the ORM listing and the Elasticsearch search.
 * It mirrors the READ rule of AttributeEntityVoter.
 */
final class AttributeEntityVisibilityResolver
{
    use ParameterValuesTrait;
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function resolve(?Operation $operation): AttributeEntityVisibility
    {
        // The editors of the requested list(s) also see the pending and rejected values
        $editableListIds = $this->getEditableListIds(null !== $operation ? self::getParameterValue($operation, 'list') : null);
        if (null !== $editableListIds) {
            return new AttributeEntityVisibility(listIds: $editableListIds);
        }

        $userIdentifier = $this->getUserOrOAuthClient()?->getUserIdentifier();
        if (!$userIdentifier) {
            return new AttributeEntityVisibility();
        }

        // Whoever may set attribute values in a workspace may pick its pending values too
        $editableWorkspaces = [];
        $workspaceIds = null !== $operation ? (array) self::getParameterValue($operation, 'workspace', []) : [];
        foreach (array_unique(array_filter(array_map(static fn (mixed $id): string => basename((string) $id), $workspaceIds))) as $workspaceId) {
            $workspace = $this->em->find(Workspace::class, $workspaceId);
            if ($workspace instanceof Workspace && $this->isGranted(AssetContainerVoterInterface::ASSET_EDIT_ATTRIBUTES, $workspace)) {
                $editableWorkspaces[] = $workspaceId;
            }
        }

        return new AttributeEntityVisibility(
            userId: $userIdentifier,
            pendingWorkspaceIds: $editableWorkspaces,
        );
    }

    /**
     * The "list" parameter: an ID or IRI, or a list of them, whatever their keys.
     *
     * @return list<string>|null the IDs of the lists, null unless every one of them is given as a string and has editable values
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
