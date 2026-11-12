<?php

declare(strict_types=1);

namespace App\Api\Traits;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\Metadata\Operation;
use App\Entity\Core\Workspace;
use App\Security\Voter\AbstractVoter;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * The scope of a listing of secrets or variables: a workspace ("workspace", to its
 * editors) or the instance ("global", to the administrators).
 *
 * The host class uses SecurityAwareTrait and provides the `$em` entity manager.
 */
trait SecretScopeTrait
{
    use ParameterValuesTrait;

    /**
     * @return Workspace|null the workspace, or null for the instance
     */
    protected function getSecretScopeWorkspace(Operation $operation): ?Workspace
    {
        if (null !== $workspaceId = self::getParameterId($operation, 'workspace')) {
            $workspace = DoctrineUtil::findStrict($this->em, Workspace::class, $workspaceId);
            $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $workspace, 'Cannot edit Workspace');

            return $workspace;
        }

        if (self::getParameterValue($operation, 'global', false)) {
            if (!$this->isAdmin()) {
                throw new AccessDeniedHttpException('The instance ones are managed by the administrators');
            }

            return null;
        }

        throw new BadRequestHttpException('You must provide "workspace" or "global"');
    }
}
