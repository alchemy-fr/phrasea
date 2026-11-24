<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Core\Workspace;
use App\Repository\Core\WorkspaceRepository;
use App\Security\Voter\WorkspaceVoter;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class WorkspaceBySlugProvider implements ProviderInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly WorkspaceRepository $workspaceRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Workspace
    {
        $slug = (string) $uriVariables['slug'];
        $workspace = $this->workspaceRepository->findOneBy([
            'slug' => $slug,
            'deletedAt' => null,
        ]);

        // A workspace the user may not read answers as an unknown one: slugs can be guessed.
        if (!$workspace instanceof Workspace || !$this->isGranted(WorkspaceVoter::READ_NO_TERMS, $workspace)) {
            throw new NotFoundHttpException(sprintf('Workspace with slug "%s" not found', $slug));
        }

        return $workspace;
    }
}
