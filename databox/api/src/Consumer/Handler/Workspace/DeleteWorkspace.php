<?php

namespace App\Consumer\Handler\Workspace;

final readonly class DeleteWorkspace
{
    public function __construct(
        private string $workspaceId
    ) {
    }

    public function getWorkspaceId(): string
    {
        return $this->workspaceId;
    }
}
