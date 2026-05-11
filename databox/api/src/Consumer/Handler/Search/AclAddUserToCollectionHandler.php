<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Search;

use App\Listener\AclCollectionIndexUpdateService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AclAddUserToCollectionHandler
{
    public function __construct(
        private AclCollectionIndexUpdateService $aclCollectionIndexUpdateService,
    ) {
    }

    public function __invoke(AclAddUserToCollection $message): void
    {
        $this->aclCollectionIndexUpdateService->addAllowedUserOrGroupToCollection(
            $message->collectionId,
            $message->userType,
            $message->userId,
        );
    }
}
