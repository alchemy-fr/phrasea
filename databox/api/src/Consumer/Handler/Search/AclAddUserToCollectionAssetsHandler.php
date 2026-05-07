<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Search;

use App\Listener\AclIndexUpdateService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AclAddUserToCollectionAssetsHandler
{
    public function __construct(
        private AclIndexUpdateService $aclIndexUpdateService,
    ) {
    }

    public function __invoke(AclAddUserToCollectionAssets $message): void
    {
        $this->aclIndexUpdateService->addAllowedUserOrGroupToCollection(
            $message->collectionId,
            $message->userType,
            $message->userId,
        );
    }
}
