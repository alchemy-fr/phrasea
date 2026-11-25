<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Core\Workspace;
use App\Service\Workspace\Template\WorkspaceTemplateOptions;
use App\Service\Workspace\WorkspaceTemplater;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:workspace:export', description: 'Export a workspace as a template.')]
class ExportWorkspaceCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly WorkspaceTemplater $workspaceTemplater,
    ) {
        parent::__construct();
    }

    public function __invoke(
        OutputInterface $output,
        #[Argument('Workspace ID to export', name: 'workspace')]
        string $workspaceId,
        #[Option('Include owners, ACEs, user/group targets and private data templates (only meaningful on the same instance)')]
        bool $withAccess = false,
        #[Option('Include the encrypted secret values (only importable on the same instance)')]
        bool $withSecrets = false,
    ): int {
        /** @var Workspace $workspace */
        $workspace = $this->em->find(Workspace::class, $workspaceId);
        if (!$workspace instanceof Workspace) {
            throw new \InvalidArgumentException(sprintf('Workspace "%s" not found', $workspaceId));
        }

        $options = new WorkspaceTemplateOptions(
            withAccessControl: $withAccess,
            withSecrets: $withSecrets,
        );

        $output->writeln(
            json_encode($this->workspaceTemplater->export($workspace, $options), JSON_PRETTY_PRINT)
        );

        return 0;
    }
}
