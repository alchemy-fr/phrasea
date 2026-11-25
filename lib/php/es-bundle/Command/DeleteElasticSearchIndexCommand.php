<?php

declare(strict_types=1);

namespace Alchemy\ESBundle\Command;

use Alchemy\ESBundle\Service\IndexRemover;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'alchemy:es:delete-index',
    description: 'Remove index and its aliases',
    help: <<<'HELP'
Physical Elasticsearch indices are resolved from the logical FOS Elastica index names.
By default the command removes the physical index currently aliased by each logical index.

The list of indices about to be deleted is displayed first and the deletion has to be
confirmed, unless <info>--force</info> is given (required in non-interactive mode).
HELP,
)]
class DeleteElasticSearchIndexCommand extends Command
{
    public function __construct(
        private readonly IndexRemover $indexRemover,
    ) {
        parent::__construct();
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        SymfonyStyle $io,
        #[Option('Logical index name (e.g. "asset"); defaults to every configured index', shortcut: 'i')]
        ?string $index = null,
        #[Option('Also remove the dated indices left behind by previous populates')]
        bool $removeOlds = false,
        #[Option('Only remove the dated indices; keep the ones currently aliased')]
        bool $oldsOnly = false,
        #[Option('Remove every physical index of the logical index ("<configured name>" or "<configured name>_<date>"), whatever its alias')]
        bool $forcePrefix = false,
        #[Option('Do not ask for confirmation', shortcut: 'f')]
        bool $force = false,
    ): int {
        $io->title(sprintf(
            'Elasticsearch index removal (%s)',
            null === $index ? 'all configured indices' : sprintf('index "%s"', $index),
        ));

        $scope = match (true) {
            $forcePrefix => 'every physical index of the logical index (configured name, dated or not), whatever its alias',
            $oldsOnly => 'only the old dated indices (currently aliased indices are kept)',
            $removeOlds => 'the currently aliased index and the old dated indices',
            default => 'the currently aliased index only',
        };
        $io->text(sprintf('Scope: %s.', $scope));

        $plan = $this->indexRemover->collectIndicesToRemove($index, $oldsOnly, $removeOlds, $forcePrefix);

        $rows = [];
        foreach ($plan as $logicalIndex => $physicalIndices) {
            foreach ($physicalIndices as $physicalIndex => $reason) {
                $rows[] = [$logicalIndex, $physicalIndex, $reason];
            }
        }

        if (empty($rows)) {
            $io->success('Nothing to remove: no matching physical index found.');

            return Command::SUCCESS;
        }

        $io->text(sprintf('The following <comment>%d</comment> physical indices will be <error>permanently deleted</error>:', count($rows)));
        $io->table(['Logical index', 'Physical index', 'Reason'], $rows);

        if (!$force) {
            if (!$input->isInteractive()) {
                $io->error('Refusing to delete indices without confirmation in non-interactive mode. Use --force to proceed.');

                return Command::FAILURE;
            }

            if (!$io->confirm('Do you really want to delete these indices? This cannot be undone.', false)) {
                $io->warning('Aborted, nothing was deleted.');

                return Command::SUCCESS;
            }
        }

        $this->indexRemover->removePlannedIndices($plan, $output);

        $io->success(sprintf('%d indices removed.', count($rows)));

        return Command::SUCCESS;
    }
}
