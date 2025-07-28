<?php

namespace App\Consumer\Handler;

use Alchemy\MessengerBundle\Attribute\MessengerMessage;

#[MessengerMessage('p2')]
final readonly class Download
{
    public function __construct(
        public string $url,
        public string $userId,
        public string $targetId,
        public string $locale,
        public ?string $schemaId,
        public ?array $data = null,
        public ?array $formData = null,
    ) {
    }
}
