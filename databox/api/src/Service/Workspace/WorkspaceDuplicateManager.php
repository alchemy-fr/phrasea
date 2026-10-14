<?php

declare(strict_types=1);

namespace App\Service\Workspace;

use App\Entity\Core\Workspace;
use App\Service\Workspace\Template\WorkspaceTemplateOptions;

/**
 * Creates a new workspace with the whole configuration of an existing one (see WorkspaceTemplater),
 * including the instance-bound data (ACEs, owners, secrets). Content (assets, collections) is not copied.
 */
readonly class WorkspaceDuplicateManager
{
    public function __construct(private WorkspaceTemplater $workspaceTemplater)
    {
    }

    public function duplicateWorkspace(Workspace $workspace, string $newSlug): Workspace
    {
        $data = $this->workspaceTemplater->export($workspace, WorkspaceTemplateOptions::full());

        $newWorkspace = new Workspace();
        $newWorkspace->setSlug($newSlug);
        $newWorkspace->setName($workspace->getName());
        $newWorkspace->setOwnerId($workspace->getOwnerId());

        $this->workspaceTemplater->importToWorkspace($newWorkspace, $data);

        return $newWorkspace;
    }
}
