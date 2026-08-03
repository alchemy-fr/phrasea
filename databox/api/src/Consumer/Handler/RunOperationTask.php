<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

#[MessengerMessage('task')]
final readonly class RunOperationTask
{
    public function __construct(
        public string $id,
    ) {

    }
}
