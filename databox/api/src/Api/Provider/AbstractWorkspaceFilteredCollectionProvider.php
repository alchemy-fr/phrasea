<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use App\Entity\Core\Workspace;
use App\Security\Voter\AbstractVoter;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

abstract class AbstractWorkspaceFilteredCollectionProvider extends AbstractCollectionProvider
{
    use SecurityAwareTrait;

    protected function getWorkspace(array $context): Workspace
    {
        $filters = $context['filters'] ?? [];
        if (!isset($filters['workspaceId'])) {
            throw new BadRequestHttpException('You must provide "workspaceId" to filter out results');
        }

        $workspace = $this->em->find(Workspace::class, $filters['workspaceId']);
        if (!$workspace instanceof Workspace) {
            throw new NotFoundHttpException(sprintf('Workspace "%s" does not exist', $workspace));
        }

        $this->denyAccessUnlessGranted(AbstractVoter::READ, $workspace, 'Cannot read Workspace');

        return $workspace;
    }
}
