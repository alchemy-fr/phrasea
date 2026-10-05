<?php

declare(strict_types=1);

namespace App\File;

final readonly class FileAnalysisUnblockResult
{
    public function __construct(
        public string $fileId,
        public string $assetId,
        public FileAnalysisUnblockAction $action,
        public string $reason,
        public ?string $workflowId = null,
    ) {
    }
}
