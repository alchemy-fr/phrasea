<?php

namespace App\Consumer\Handler\Attribute;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

#[MessengerMessage('p1')]
final readonly class AttributeMigrateToEntityList
{
    public function __construct(
        public string $id,
    ) {

    }
}
