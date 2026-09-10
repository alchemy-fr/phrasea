<?php

declare(strict_types=1);

namespace App\Command;

use App\Elasticsearch\PopulateLockManager;
use FOS\ElasticaBundle\Index\IndexManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:es:populate-unlock',
    description: 'Force-release the populate lock of one or every index (after a worker died mid-populate)',
)]
final class ESPopulateUnlockCommand extends Command
{
    public function __construct(
        private readonly PopulateLockManager $lockManager,
        private readonly IndexManager $indexManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('index', InputArgument::OPTIONAL, 'Logical index name (e.g. "asset"); defaults to every locked index')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Do not ask for confirmation')
            ->setHelp(<<<'HELP'
Each populate takes a lock on its index so that two processes never populate the same index at once.
The lock is released at the end of the run, or expires by itself 30 minutes after the last inserted page.

Use this command when a worker was killed mid-populate and you cannot wait: the lock is removed and the
populate passes still open for the index are closed with an error.

<error>Never release the lock of a populate that is actually still running</error>: the next populate of the same
index would delete its pass and its half-built physical index.
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $indices = array_keys($this->indexManager->getAllIndexes());
        $indexArg = $input->getArgument('index');
        if (null !== $indexArg && !\in_array($indexArg, $indices, true)) {
            $io->error(\sprintf('Unknown index "%s". Known indices: %s', $indexArg, implode(', ', $indices)));

            return Command::FAILURE;
        }

        $locked = $this->lockManager->getLockedIndices(null === $indexArg ? $indices : [$indexArg]);
        if ([] === $locked) {
            $io->success(null === $indexArg ? 'No populate lock is held.' : \sprintf('No populate lock is held for index "%s".', $indexArg));

            return Command::SUCCESS;
        }

        $io->warning(\sprintf('Populate lock(s) currently held: %s', implode(', ', $locked)));
        $io->text('Releasing a lock while its populate is still running will make that populate fail and lose its index.');

        if (true !== $input->getOption('force')) {
            if (!$input->isInteractive()) {
                $io->error('Refusing to release locks without confirmation in non-interactive mode. Use --force to proceed.');

                return Command::FAILURE;
            }
            if (!$io->confirm('Are you sure no populate is running for these indices? Release the lock(s)?', false)) {
                $io->warning('Aborted, nothing was released.');

                return Command::SUCCESS;
            }
        }

        foreach ($locked as $index) {
            $result = $this->lockManager->forceRelease($index);
            $io->text(\sprintf(
                'Index <comment>%s</comment>: lock %s, %d open pass(es) closed',
                $index,
                $result['lockReleased'] ? '<info>released</info>' : 'already gone',
                $result['passesClosed'],
            ));
        }

        $io->success(\sprintf('%d lock(s) released.', \count($locked)));

        return Command::SUCCESS;
    }
}
