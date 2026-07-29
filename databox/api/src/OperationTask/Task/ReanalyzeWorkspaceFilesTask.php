<?php

declare(strict_types=1);

namespace App\OperationTask\Task;

use App\Border\WorkspaceFileReanalyzer;
use App\OperationTask\OperationTaskInterface;
use App\OperationTask\RunContext;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final readonly class ReanalyzeWorkspaceFilesTask implements OperationTaskInterface
{
    public function __construct(
        private WorkspaceFileReanalyzer $workspaceFileReanalyzer,
    ) {
    }

    public static function getName(): string
    {
        return 'reanalyze_workspace_files';
    }

    public function validate(array $payload): void
    {
        if (empty($payload['workspaceId'] ?? null)) {
            throw new BadRequestHttpException('workspaceId is required');
        }
    }

    public function handle(array $payload, RunContext $context): void
    {
        $this->workspaceFileReanalyzer->reanalyzeWorkspaceFiles(
            $payload['workspaceId'],
            $context,
        );
    }
}
