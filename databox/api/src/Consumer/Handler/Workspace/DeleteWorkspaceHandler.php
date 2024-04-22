<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Workspace;

use App\Doctrine\Delete\WorkspaceDelete;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DeleteWorkspaceHandler
{
    public function __construct(
        private WorkspaceDelete $workspaceDelete,
    ) {
    }

    public function __invoke(DeleteWorkspace $message): void
    {
        $this->workspaceDelete->deleteWorkspace($message->getWorkspaceId());
    }
}
