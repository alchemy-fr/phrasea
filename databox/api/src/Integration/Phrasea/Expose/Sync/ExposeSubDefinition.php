<?php

namespace App\Integration\Phrasea\Expose\Sync;

final readonly class ExposeSubDefinition
{
    public function __construct(
        public string $id,
        public string $subDefinitionName,
        public string $renditionId,
        public string $fileId,
    ) {
    }
}
