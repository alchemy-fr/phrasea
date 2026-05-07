<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Search;

use App\Listener\AclIndexUpdateService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AclAddUserToWorkspaceAssetsHandler
{
    public function __construct(
        private AclIndexUpdateService $aclIndexUpdateService,
    ) {
    }

    public function __invoke(AclAddUserToWorkspaceAssets $message): void
    {
        $this->aclIndexUpdateService->addAllowedUserOrGroupToWorkspace(
            $message->workspaceId,
            $message->userType,
            $message->userId,
        );
    }
}
