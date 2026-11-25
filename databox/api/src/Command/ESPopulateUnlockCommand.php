<?php

declare(strict_types=1);

namespace App\Command;

use App\Elasticsearch\Exception\PopulateOwnerAliveException;
use App\Elasticsearch\PopulateLockManager;
use FOS\ElasticaBundle\Index\IndexManager;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:es:populate-unlock',
    description: 'Force-release the populate lock of one or every index (after a worker died mid-populate)',
    help: <<<'HELP'
Each populate takes a lock on its index so that two processes never populate the same index at once.
The lock is released at the end of the run, or expires by itself 30 minutes after the last heartbeat of its
populate (renewed while it indexes).

Use this command when a worker was killed mid-populate and you cannot wait: the lock is removed and the
populate passes still open for the index are closed with an error. The lock of a populate whose last
heartbeat is less than 5 minutes old is kept, unless --even-if-alive is given.

<error>Never release the lock of a populate that is actually still running</error>: another populate of the same
index could then run alongside it.
HELP,
)]
final class ESPopulateUnlockCommand extends Command
{
    public function __construct(
        private readonly PopulateLockManager $lockManager,
        private readonly IndexManager $indexManager,
    ) {
        parent::__construct();
    }

    public function __invoke(
        InputInterface $input,
        SymfonyStyle $io,
        #[Argument('Logical index name (e.g. "asset"); defaults to every locked index', name: 'index')]
        ?string $indexArg = null,
        #[Option('Do not ask for confirmation', shortcut: 'f')]
        bool $force = false,
        #[Option('Also release the lock of a populate that gave a sign of life recently')]
        bool $evenIfAlive = false,
    ): int {
        $indices = array_keys($this->indexManager->getAllIndexes());
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

        if (!$force) {
            if (!$input->isInteractive()) {
                $io->error('Refusing to release locks without confirmation in non-interactive mode. Use --force to proceed.');

                return Command::FAILURE;
            }
            if (!$io->confirm('Are you sure no populate is running for these indices? Release the lock(s)?', false)) {
                $io->warning('Aborted, nothing was released.');

                return Command::SUCCESS;
            }
        }

        $released = 0;
        foreach ($locked as $index) {
            try {
                $result = $this->lockManager->forceRelease($index, evenIfAlive: $evenIfAlive);
            } catch (PopulateOwnerAliveException $e) {
                $io->error($e->getMessage().' Use --even-if-alive to release it anyway.');

                continue;
            }
            ++$released;
            $io->text(\sprintf(
                'Index <comment>%s</comment>: lock %s, %d open pass(es) closed',
                $index,
                $result['lockReleased'] ? '<info>released</info>' : 'already gone',
                $result['passesClosed'],
            ));
        }

        if ($released < \count($locked)) {
            $io->warning(\sprintf('%d lock(s) released, %d kept.', $released, \count($locked) - $released));

            return Command::FAILURE;
        }

        $io->success(\sprintf('%d lock(s) released.', $released));

        return Command::SUCCESS;
    }
}
