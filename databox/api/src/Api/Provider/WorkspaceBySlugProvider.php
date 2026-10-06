<?php

declare(strict_types=1);

namespace App\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Core\Workspace;
use App\Repository\Core\WorkspaceRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class WorkspaceBySlugProvider implements ProviderInterface
{
    public function __construct(
        private WorkspaceRepository $workspaceRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Workspace
    {
        $slug = (string) $uriVariables['slug'];
        $workspace = $this->workspaceRepository->findOneBy([
            'slug' => $slug,
            'deletedAt' => null,
        ]);

        if (!$workspace instanceof Workspace) {
            throw new NotFoundHttpException(sprintf('Workspace with slug "%s" not found', $slug));
        }

        return $workspace;
    }
}
