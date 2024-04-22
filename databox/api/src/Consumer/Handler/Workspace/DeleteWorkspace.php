<?php

namespace App\Consumer\Handler\Workspace;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

#[MessengerMessage('p2')]
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
