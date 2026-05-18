<?php

namespace App\Consumer\Handler\Search;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

#[MessengerMessage('p1')]
final readonly class AclAddUserToCollectionAssets
{
    public function __construct(
        public string $collectionId,
        public int $userType,
        public string $userId,
    ) {
    }
}
