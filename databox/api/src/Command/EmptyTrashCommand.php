<?php

declare(strict_types=1);

namespace App\Command;

use App\Doctrine\Delete\TrashCleaner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('app:trash:empty', 'Empty the trash of a all workspaces')]
class EmptyTrashCommand extends Command
{
    public function __construct(
        private readonly TrashCleaner $trashCleaner,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->trashCleaner->emptyTrash();

        return Command::SUCCESS;
    }
}
