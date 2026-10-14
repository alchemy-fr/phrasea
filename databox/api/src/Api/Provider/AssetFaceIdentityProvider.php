<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use App\Api\EntityIriConverter;
use App\Api\Traits\WorkspaceCollectionTrait;
use App\Repository\Core\AssetFaceRepository;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * The persons named in the readable workspaces (or in the "workspace" parameter), with the
 * number of faces carrying each name, plus the number of faces still unnamed.
 */
final class AssetFaceIdentityProvider extends AbstractCollectionProvider
{
    use SecurityAwareTrait;
    use WorkspaceCollectionTrait;

    public function __construct(
        private readonly AssetFaceRepository $faceRepository,
        private readonly EntityIriConverter $entityIriConverter,
    ) {
    }

    protected function provideCollection(Operation $operation, array $uriVariables = [], array $context = []): array|object
    {
        $workspaces = $this->resolveAllowedWorkspaces($operation);

        $query = self::getParameterValue($operation, 'query');
        $query = \is_string($query) && '' !== trim($query) ? trim($query) : null;

        return new JsonResponse([
            'identities' => !empty($workspaces) ? $this->faceRepository->getIdentities($workspaces, $query) : [],
            'unnamed' => !empty($workspaces) ? $this->faceRepository->countUnnamed($workspaces) : 0,
        ]);
    }
}
