<?php

declare(strict_types=1);

namespace App\Command;

use App\Doctrine\Delete\WorkspaceDelete;
use App\Entity\Core\Workspace;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

#[AsCommand(name: 'app:workspace:delete', description: 'Delete a workspace')]
class DeleteWorkspaceCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly WorkspaceDelete $workspaceDelete,
    ) {
        parent::__construct();
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Argument]
        string $workspaceId,
        #[Option('Force the deletion without asking for confirmation', shortcut: 'f')]
        bool $force = false,
    ): int {
        $workspace = $this->em->find(Workspace::class, $workspaceId);
        if (!$workspace instanceof Workspace) {
            // Idempotent: the deletion job may be replayed after a successful run.
            $output->writeln(sprintf('<comment>Workspace "%s" not found (already deleted?), nothing to do.</comment>', $workspaceId));

            return Command::SUCCESS;
        }

        if (!$force) {
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
            $this->em->persist($workspace);
            $this->em->flush();
        }

        $this->workspaceDelete->deleteWorkspace($workspaceId);
        $output->writeln('Done.');

        return 0;
    }
}
