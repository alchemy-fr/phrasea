<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Search;

use App\Listener\AclAssetIndexUpdateService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AclAddUserToCollectionAssetsHandler
{
    public function __construct(
        private AclAssetIndexUpdateService $aclAssetIndexUpdateService,
    ) {
    }

    public function __invoke(AclAddUserToCollectionAssets $message): void
    {
        $this->aclAssetIndexUpdateService->addAllowedUserOrGroupToCollection(
            $message->collectionId,
            $message->userType,
            $message->userId,
        );
    }
}
