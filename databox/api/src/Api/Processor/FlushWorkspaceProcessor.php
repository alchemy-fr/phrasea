<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Core\Workspace;
use App\Service\Workspace\WorkspaceDuplicateManager;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Replaces the workspace by an empty copy of its configuration.
 */
final readonly class FlushWorkspaceProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private WorkspaceDuplicateManager $workspaceManager,
    ) {
    }

    /**
     * @param Workspace $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Workspace
    {
        return $this->em->wrapInTransaction(function () use ($data): Workspace {
            $slug = $data->getSlug();
            $data->setSlug('del-'.$data->getId());
            $this->em->flush();

            $newWorkspace = $this->workspaceManager->duplicateWorkspace($data, $slug);

            $this->em->remove($data);
            $this->em->flush();

            return $newWorkspace;
        });
    }
}
