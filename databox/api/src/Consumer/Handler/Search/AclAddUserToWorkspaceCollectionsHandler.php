<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Search;

use App\Listener\AclCollectionIndexUpdateService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AclAddUserToWorkspaceCollectionsHandler
{
    public function __construct(
        private AclCollectionIndexUpdateService $aclCollectionIndexUpdateService,
    ) {
    }

    public function __invoke(AclAddUserToWorkspaceCollections $message): void
    {
        $this->aclCollectionIndexUpdateService->addAllowedUserOrGroupToWorkspace(
            $message->workspaceId,
            $message->userType,
            $message->userId,
        );
    }
}
