<?php

declare(strict_types=1);

namespace App\Command;

use App\Doctrine\Delete\WorkspaceDelete;
use App\Entity\Core\Workspace;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

#[AsCommand('app:workspace:delete')]
class DeleteWorkspaceCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly WorkspaceDelete $workspaceDelete,
        private readonly LoggerInterface $logger)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();

        $this
            ->setDescription('Delete a workspace')
            ->addArgument('workspace-id', InputArgument::REQUIRED)
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force the deletion without asking for confirmation')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workspaceId = $input->getArgument('workspace-id');
        $workspace = $this->em->find(Workspace::class, $workspaceId);
        if (!$workspace instanceof Workspace) {
            throw new \InvalidArgumentException(sprintf('Workspace "%s" not found', $workspaceId));
        }

        if (!$input->getOption('force')) {
            $output->writeln(sprintf('This action will delete the workspace "%s" (%s) and all its content.', $workspace->getName(), $workspace->getId()));
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('Continue? (y/n) ', false);
            if (!$helper->ask($input, $output, $question)) {
                return 0;
            }
        }

        $output->writeln(sprintf('Deleting workspace "%s" (%s)...', $workspace->getName(), $workspace->getId()));

        if (null === $workspace->getDeletedAt()) {
            $workspace->setDeletedAt(new \DateTimeImmutable());
            $this->em->flush();
        }

        $this->workspaceDelete->deleteWorkspace($workspaceId, $this->logger);
        $output->writeln('Done.');

        return 0;
    }
}
