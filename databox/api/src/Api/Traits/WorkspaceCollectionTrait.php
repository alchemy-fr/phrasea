<?php

declare(strict_types=1);

namespace App\Api\Traits;

use ApiPlatform\Exception\ItemNotFoundException;
use App\Entity\Core\Workspace;
use App\Security\Voter\AbstractVoter;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

trait WorkspaceCollectionTrait
{
    protected function resolveAllowedWorkspaces(array &$context): array
    {
        $filter = $context['filters']['workspace'] ?? null;

        // The `workspace` filter is documented as a single IRI/id, but a client
        // sending `?workspace[]=…` hands us a list — resolve each rather than
        // passing an array where a string is expected.
        $workspaceIds = array_values(array_filter(
            \is_array($filter) ? $filter : [$filter],
            static fn (mixed $v): bool => \is_string($v) && '' !== $v
        ));

        if (empty($workspaceIds)) {
            $user = $this->getUser();
            $workspaces = $this->em->getRepository(Workspace::class)->getAllowedWorkspaceIds($user?->getId(), $user?->getGroups() ?? [], $this->isAdmin());
        } else {
            $workspaces = [];
            foreach ($workspaceIds as $workspaceId) {
                try {
                    $workspace = $this->entityIriConverter->getItemFromIri(Workspace::class, $workspaceId);
                } catch (ItemNotFoundException $e) {
                    throw new BadRequestHttpException(sprintf('Workspace "%s" not found', $workspaceId), $e);
                }
                $this->denyAccessUnlessGranted(AbstractVoter::READ, $workspace);
                $workspaces[] = $workspace->getId();
            }
        }

        return $context['filters']['workspace'] = $workspaces;
    }
}
