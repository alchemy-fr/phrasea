<?php

namespace App\Api\Traits;

use App\Entity\Core\Workspace;
use App\Security\Voter\AbstractVoter;

trait WorkspaceCollectionTrait
{
    protected function resolveAllowedWorkspaces(array &$context): array
    {
        $workspaceId = $context['filters']['workspace'] ?? null;
        if (!$workspaceId) {
            $user = $this->getUser();
            $workspaces = $this->em->getRepository(Workspace::class)->getAllowedWorkspaceIds($user?->getId(), $user?->getGroups() ?? []);
        } else {
            $workspace = $this->entityIriConverter->getItemFromIri(Workspace::class, $workspaceId);
            $this->denyAccessUnlessGranted(AbstractVoter::READ, $workspace);
            $workspaces = [$workspaceId];
        }

        return $context['filters']['workspace'] = $workspaces;
    }
}
