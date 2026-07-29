<?php

declare(strict_types=1);

namespace App\Border;

use App\Entity\Core\File;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\Core\FileAnalyzer\FileAnalyzerIntegration;
use App\Integration\IntegrationManager;
use App\OperationTask\RunContext;
use App\Repository\Core\FileRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class WorkspaceFileReanalyzer
{
    private const int BATCH_SIZE = 100;

    public function __construct(
        private FileAnalyzer $fileAnalyzer,
        private IntegrationManager $integrationManager,
        private FileRepository $fileRepository,
        private EntityManagerInterface $em,
    ) {
    }

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

        $total = $this->fileRepository->countByWorkspace($workspaceId);
        if (0 === $total) {
            return;
        }

        $context->start($total);

        $offset = 0;
        while (true) {
            $count = 0;
            foreach ($this->fileRepository->iterateByWorkspace($workspaceId, self::BATCH_SIZE, $offset) as $file) {
                $this->reanalyzeFile($file, $analyzersConfig, $context);
                ++$count;
            }

            $this->em->flush();
            $this->em->clear();
            $context->advance($count);
            $offset += $count;

            if ($count < self::BATCH_SIZE) {
                break;
            }
        }

        $context->finish();
    }

    /**
     * @param array{analyzers: array<mixed>} $analyzersConfig
     */
    private function reanalyzeFile(File $file, array $analyzersConfig, RunContext $context): void
    {
        try {
            if ($this->fileAnalyzer->preAnalyzeFile($file, $analyzersConfig, force: true)) {
                $this->fileAnalyzer->analyzeFile($file, $analyzersConfig, force: true);
            }
            $this->em->persist($file);
        } catch (\Throwable $e) {
            $context->getOutput()->writeln(sprintf(
                '<error>Failed to analyze file %s: %s</error>',
                $file->getId(),
                $e->getMessage(),
            ));
        }
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
