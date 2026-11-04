<?php

declare(strict_types=1);

namespace App\Consumer\Handler\File;

use App\File\FileAnalysisReevaluator;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class RestoreDuplicateLinksHandler
{
    public function __construct(
        private FileAnalysisReevaluator $fileAnalysisReevaluator,
    ) {
    }

    public function __invoke(RestoreDuplicateLinks $message): void
    {
        $this->fileAnalysisReevaluator->restoreDuplicateLinks($message->getFileIds());
    }
}
