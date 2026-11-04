<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use App\Api\Traits\ParameterValuesTrait;
use App\Entity\Core\Workspace;
use App\Security\Voter\AbstractVoter;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

abstract class AbstractWorkspaceFilteredCollectionProvider extends AbstractCollectionProvider
{
    use ParameterValuesTrait;
    use SecurityAwareTrait;

    /**
     * The workspace of the "workspaceId" parameter (an ID or IRI), which is mandatory.
     */
    protected function getWorkspace(Operation $operation): Workspace
    {
        $workspaceId = self::getParameterId($operation, 'workspaceId')
            ?? throw new BadRequestHttpException('You must provide "workspaceId" to filter out results');

        $workspace = $this->em->find(Workspace::class, $workspaceId);
        if (!$workspace instanceof Workspace) {
            throw new NotFoundHttpException(sprintf('Workspace "%s" does not exist', $workspaceId));
        }

        $this->denyAccessUnlessGranted(AbstractVoter::READ, $workspace, 'Cannot read Workspace');

        return $workspace;
    }
}
