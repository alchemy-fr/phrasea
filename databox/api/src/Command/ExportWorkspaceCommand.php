<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Core\Workspace;
use App\Workspace\WorkspaceTemplater;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('app:workspace:export')]
class ExportWorkspaceCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly WorkspaceTemplater     $workspaceTemplater,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();

        $this
            ->setDescription('Export a workspace as a template.')
            ->addArgument('workspace', InputOption::VALUE_REQUIRED, 'Workspace ID to export')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var Workspace $workspace */
        $workspace = $this->em->find(Workspace::class, $input->getArgument('workspace'));
        if (!$workspace instanceof Workspace) {
            throw new \InvalidArgumentException(sprintf('Workspace "%s" not found', $input->getArgument('workspace')));
        }

        $output->writeln($this->workspaceTemplater->export($workspace));

        return 0;
    }
}
