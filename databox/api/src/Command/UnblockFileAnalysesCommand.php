<?php

declare(strict_types=1);

namespace App\Command;

use App\File\FileAnalysisUnblockAction;
use App\File\FileAnalysisUnblocker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Meant to run periodically (hourly cron): finds the source files whose
 * analysis never completed and re-triggers it (see FileAnalysisUnblocker).
 */
#[AsCommand('app:file:unblock-analyses', 'Re-trigger the analysis of files stuck in the pending state')]
final class UnblockFileAnalysesCommand extends Command
{
    public function __construct(
        private readonly FileAnalysisUnblocker $fileAnalysisUnblocker,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'older-than',
                't',
                InputOption::VALUE_REQUIRED,
                'Consider stuck the pending analyses whose file/workflow is older than this number of seconds',
                3600,
            )
            ->addOption(
                'limit',
                'l',
                InputOption::VALUE_REQUIRED,
                'Maximum number of files handled per run',
                FileAnalysisUnblocker::DEFAULT_LIMIT,
            )
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only report what would be done');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $olderThan = (int) $input->getOption('older-than');
        $limit = (int) $input->getOption('limit');
        $dryRun = (bool) $input->getOption('dry-run');

        if ($olderThan < 0 || $limit <= 0) {
            $io->error('"older-than" must be >= 0 and "limit" must be > 0');

            return Command::INVALID;
        }

        $before = new \DateTimeImmutable(sprintf('-%d seconds', $olderThan));
        $results = $this->fileAnalysisUnblocker->unblock($before, $limit, $dryRun);

        $counts = array_fill_keys(array_map(fn (FileAnalysisUnblockAction $a) => $a->value, FileAnalysisUnblockAction::cases()), 0);
        $rows = [];
        foreach ($results as $result) {
            ++$counts[$result->action->value];
            if ($output->isVerbose() || FileAnalysisUnblockAction::Skipped !== $result->action) {
                $rows[] = [$result->fileId, $result->assetId, $result->workflowId ?? '-', $result->action->value, $result->reason];
            }
        }

        if (!empty($rows)) {
            $io->table(['File', 'Asset', 'Workflow', 'Action', 'Reason'], $rows);
        }

        $io->writeln(sprintf(
            '%s%d pending file(s) inspected: %d ingest dispatched, %d analyzer job rerun, %d failed jobs retried, %d skipped',
            $dryRun ? '[dry-run] ' : '',
            count($results),
            $counts[FileAnalysisUnblockAction::IngestDispatched->value],
            $counts[FileAnalysisUnblockAction::JobRerun->value],
            $counts[FileAnalysisUnblockAction::FailedJobsRetried->value],
            $counts[FileAnalysisUnblockAction::Skipped->value],
        ));

        return Command::SUCCESS;
    }
}
