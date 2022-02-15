<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Core\Workspace;
use App\Workspace\WorkspaceDuplicateManager;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DuplicateWorkspaceCommand extends Command
{
    private WorkspaceDuplicateManager $workspaceDuplicateManager;
    private EntityManagerInterface $em;

    public function __construct(WorkspaceDuplicateManager $workspaceDuplicateManager, EntityManagerInterface $em)
    {
        parent::__construct();

        $this->workspaceDuplicateManager = $workspaceDuplicateManager;
        $this->em = $em;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('app:workspace:duplicate')
            ->setDescription('Create a new workspace from an existing one and copy its settings')
            ->addArgument('workspace-id', InputArgument::REQUIRED)
            ->addArgument('new-slug', InputArgument::REQUIRED)
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $workspaceId = $input->getArgument('workspace-id');
        $newSlug = $input->getArgument('new-slug');
        $workspace = $this->em->find(Workspace::class, $workspaceId);

        if (!$workspace instanceof Workspace) {
            throw new InvalidArgumentException('Workspace '.$workspaceId.' not found');
        }

        $this->workspaceDuplicateManager->duplicateWorkspace($workspace, $newSlug);
        $this->em->flush();

        $output->writeln('Done.');

        return 0;
    }
}
