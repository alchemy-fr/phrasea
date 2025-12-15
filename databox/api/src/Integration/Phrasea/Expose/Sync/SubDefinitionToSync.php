<?php

namespace App\Integration\Phrasea\Expose\Sync;

final readonly class SubDefinitionToSync
{
    public function __construct(
        public string $exposeId,
        public string $databoxRenditionId,
    ) {
    }
}
