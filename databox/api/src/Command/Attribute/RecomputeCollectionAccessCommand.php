<?php

declare(strict_types=1);

namespace App\Command\Attribute;

use App\Service\Collection\CollectionAccessService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:collection:recompute-access',
    description: 'Collection indexer'
)]
class RecomputeCollectionAccessCommand extends Command
{
    public function __construct(
        private readonly CollectionAccessService $collectionAccessService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->collectionAccessService->recomputeAll($output);

        return Command::SUCCESS;
    }
}
