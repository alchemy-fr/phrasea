<?php

declare(strict_types=1);

namespace Alchemy\ESBundle\Command;

use Alchemy\ESBundle\Service\IndexRemover;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DeleteElasticSearchIndexCommand extends Command
{
    public function __construct(
        private readonly IndexRemover $indexRemover,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('alchemy:es:delete-index')
            ->addOption('index', 'i', InputOption::VALUE_REQUIRED, 'Logical index name (e.g. "asset"); defaults to every configured index')
            ->addOption('remove-olds', null, InputOption::VALUE_NONE, 'Also remove the dated indices left behind by previous populates')
            ->addOption('olds-only', null, InputOption::VALUE_NONE, 'Only remove the dated indices; keep the ones currently aliased')
            ->addOption('force-prefix', null, InputOption::VALUE_NONE, 'Remove every physical index whose name starts with "<index>_", whatever its alias')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Do not ask for confirmation')
            ->setDescription('Remove index and its aliases')
            ->setHelp(<<<'HELP'
Physical Elasticsearch indices are resolved from the logical FOS Elastica index names.
By default the command removes the physical index currently aliased by each logical index.

The list of indices about to be deleted is displayed first and the deletion has to be
confirmed, unless <info>--force</info> is given (required in non-interactive mode).
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $indexArg = $input->getOption('index');
        $oldsOnly = true === $input->getOption('olds-only');
        $removeOlds = true === $input->getOption('remove-olds');
        $forcePrefix = true === $input->getOption('force-prefix');

        $io->title(sprintf(
            'Elasticsearch index removal (%s)',
            null === $indexArg ? 'all configured indices' : sprintf('index "%s"', $indexArg),
        ));

        $scope = match (true) {
            $forcePrefix => 'every physical index prefixed by the logical index name',
            $oldsOnly => 'only the old dated indices (currently aliased indices are kept)',
            $removeOlds => 'the currently aliased index and the old dated indices',
            default => 'the currently aliased index only',
        };
        $io->text(sprintf('Scope: %s.', $scope));

        $plan = $this->indexRemover->collectIndicesToRemove($indexArg, $oldsOnly, $removeOlds, $forcePrefix);

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

        if (true !== $input->getOption('force')) {
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
