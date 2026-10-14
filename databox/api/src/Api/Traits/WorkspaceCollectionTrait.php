<?php

declare(strict_types=1);

namespace App\Api\Traits;

use ApiPlatform\Metadata\Exception\ItemNotFoundException;
use ApiPlatform\Metadata\Operation;
use App\Entity\Core\Workspace;
use App\Security\Voter\AbstractVoter;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

trait WorkspaceCollectionTrait
{
    use ParameterValuesTrait;

    /**
     * Resolves the workspaces the collection is restricted to: the "workspace" parameter
     * (IRIs or IDs, each checked for READ access) or, without it, every readable workspace.
     * The resolved IDs are set back on the parameter, so that its filter applies them on
     * either engine.
     *
     * @return list<string>
     */
    protected function resolveAllowedWorkspaces(Operation $operation): array
    {
        $filter = self::getParameterValue($operation, 'workspace');

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

        $workspaces = array_values($workspaces);

        // Restricts the collection to these workspaces through the "workspace" filter parameter
        $workspaceParameter = $operation->getParameters()?->get('workspace') ?? throw new \LogicException('Missing "workspace" parameter on the collection');
        $workspaceParameter->setValue($workspaces);

        return $workspaces;
    }
}
