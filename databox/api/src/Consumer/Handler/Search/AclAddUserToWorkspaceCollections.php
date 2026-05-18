<?php

namespace App\Consumer\Handler\Search;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

#[MessengerMessage('p1')]
final readonly class AclAddUserToWorkspaceCollections
{
    public function __construct(
        public ?string $workspaceId,
        public int $userType,
        public string $userId,
    ) {
    }
}
