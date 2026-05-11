<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Search;

use App\Listener\AclAssetIndexUpdateService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AclAddUserToWorkspaceAssetsHandler
{
    public function __construct(
        private AclAssetIndexUpdateService $aclAssetIndexUpdateService,
    ) {
    }

    public function __invoke(AclAddUserToWorkspaceAssets $message): void
    {
        $this->aclAssetIndexUpdateService->addAllowedUserOrGroupToWorkspace(
            $message->workspaceId,
            $message->userType,
            $message->userId,
        );
    }
}
