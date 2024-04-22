<?php

namespace App\Consumer\Handler\Workspace;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

#[MessengerMessage('p1')]
final readonly class OnWorkspaceDelete
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
