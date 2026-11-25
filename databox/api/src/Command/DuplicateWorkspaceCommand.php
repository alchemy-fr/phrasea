<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Core\Workspace;
use App\Service\Workspace\WorkspaceDuplicateManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:workspace:duplicate',
    description: 'Create a new workspace from an existing one and copy its settings'
)]
class DuplicateWorkspaceCommand extends Command
{
    public function __construct(private readonly WorkspaceDuplicateManager $workspaceDuplicateManager, private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    public function __invoke(
        OutputInterface $output,
        #[Argument]
        string $workspaceId,
        #[Argument]
        string $newSlug,
    ): int {
        $workspace = $this->em->find(Workspace::class, $workspaceId);

        if (!$workspace instanceof Workspace) {
            throw new \InvalidArgumentException('Workspace '.$workspaceId.' not found');
        }

        $this->workspaceDuplicateManager->duplicateWorkspace($workspace, $newSlug);
        $this->em->flush();

        $output->writeln('Done.');

        return 0;
    }
}
