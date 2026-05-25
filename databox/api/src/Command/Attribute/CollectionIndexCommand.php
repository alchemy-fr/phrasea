<?php

declare(strict_types=1);

namespace App\Command\Attribute;

use App\Service\Collection\CollectionAccessService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CollectionIndexCommand extends Command
{
    public function __construct(
        private readonly CollectionAccessService $collectionAccessService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('app:es:index-collections')
            ->setDescription('Collection indexer');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->collectionAccessService->recomputeAll($output);

        return Command::SUCCESS;
    }
}
