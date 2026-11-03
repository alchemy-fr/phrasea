<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Import;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

#[MessengerMessage('task')]
final readonly class DispatchAssetImport
{
    public function __construct(
        public string $importId,
    ) {
    }
}
