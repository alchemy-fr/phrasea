<?php

declare(strict_types=1);

namespace App\Border;

use App\Consumer\Handler\ReanalyzeWorkspaceFile;
use App\Entity\Core\File;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\Core\FileAnalyzer\FileAnalyzerIntegration;
use App\Integration\IntegrationManager;
use App\OperationTask\OperationTaskProgress;
use App\OperationTask\RunContext;
use App\Repository\Core\FileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class WorkspaceFileReanalyzer
{
    public function __construct(
        private FileAnalyzer $fileAnalyzer,
        private IntegrationManager $integrationManager,
        private FileRepository $fileRepository,
        private EntityManagerInterface $em,
        private MessageBusInterface $bus,
        private OperationTaskProgress $taskProgress,
    ) {
    }

    /**
     * Fan out the re-analysis: dispatch one message per file so the main task
     * worker is released immediately. Each message re-analyzes its file and
     * updates the task progress; the last one completes the task.
     */
    public function reanalyzeWorkspaceFiles(string $workspaceId, RunContext $context): void
    {
        $analyzersConfig = $this->resolveAnalyzersConfig($workspaceId);
        if (empty($analyzersConfig['analyzers'])) {
            $context->getOutput()->writeln(sprintf(
                '<comment>No enabled "%s" integration found for this workspace. Nothing to analyze.</comment>',
                FileAnalyzerIntegration::getName(),
            ));

            return;
        }

        $taskId = $context->getTaskId();
        $this->taskProgress->init($taskId);

        $dispatched = 0;
        foreach ($this->fileRepository->iterateIdsByWorkspace($workspaceId) as $fileId) {
            $this->bus->dispatch(new ReanalyzeWorkspaceFile($taskId, $fileId));
            ++$dispatched;
        }

        if (0 === $dispatched) {
            return;
        }

        $context->getOutput()->writeln(sprintf('<info>Dispatched %d file(s) for re-analysis.</info>', $dispatched));

        // Publish the authoritative total, then complete the task in case
        // every dispatched message was already processed.
        $this->taskProgress->setItemTotal($taskId, $dispatched);
        $this->taskProgress->finalizeIfComplete($taskId);

        $context->deferCompletion();
    }

    /**
     * Force a re-run of the file analysis on a single file.
     */
    public function reanalyzeFile(File $file): void
    {
        $analyzersConfig = $this->resolveAnalyzersConfig($file->getWorkspaceId());
        if (empty($analyzersConfig['analyzers'])) {
            return;
        }

        if ($this->fileAnalyzer->preAnalyzeFile($file, $analyzersConfig, force: true)) {
            $this->fileAnalyzer->analyzeFile($file, $analyzersConfig, force: true);
        }

        $this->em->persist($file);
    }

    /**
     * Merge the analyzers of every enabled core.file_analyzer integration of the workspace.
     *
     * @return array{analyzers: array<mixed>}
     */
    private function resolveAnalyzersConfig(string $workspaceId): array
    {
        /** @var WorkspaceIntegration[] $integrations */
        $integrations = $this->em->getRepository(WorkspaceIntegration::class)->findBy([
            'workspace' => $workspaceId,
            'integration' => FileAnalyzerIntegration::getName(),
            'enabled' => true,
        ]);

        $analyzers = [];
        foreach ($integrations as $workspaceIntegration) {
            $config = $this->integrationManager->getIntegrationConfiguration($workspaceIntegration);
            foreach ($config['analyzers'] ?? [] as $analyzer) {
                $analyzers[] = $analyzer;
            }
        }

        return ['analyzers' => $analyzers];
    }
}
