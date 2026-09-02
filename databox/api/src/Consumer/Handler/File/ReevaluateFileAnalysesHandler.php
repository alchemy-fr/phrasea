<?php

declare(strict_types=1);

namespace App\Consumer\Handler\File;

use App\File\FileAnalysisReevaluator;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class ReevaluateFileAnalysesHandler
{
    public function __construct(
        private FileAnalysisReevaluator $fileAnalysisReevaluator,
    ) {
    }

    public function __invoke(ReevaluateFileAnalyses $message): void
    {
        $this->fileAnalysisReevaluator->reevaluateFiles($message->getFileIds());
    }
}
