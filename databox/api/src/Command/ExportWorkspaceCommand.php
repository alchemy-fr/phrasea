<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Core\Workspace;
use App\Service\Workspace\Template\WorkspaceTemplateOptions;
use App\Service\Workspace\WorkspaceTemplater;
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
        private readonly WorkspaceTemplater $workspaceTemplater,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();

        $this
            ->setDescription('Export a workspace as a template.')
            ->addArgument('workspace', InputOption::VALUE_REQUIRED, 'Workspace ID to export')
            ->addOption('with-access', null, InputOption::VALUE_NONE, 'Include owners, ACEs, user/group targets and private data templates (only meaningful on the same instance)')
            ->addOption('with-secrets', null, InputOption::VALUE_NONE, 'Include the encrypted secret values (only importable on the same instance)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var Workspace $workspace */
        $workspace = $this->em->find(Workspace::class, $input->getArgument('workspace'));
        if (!$workspace instanceof Workspace) {
            throw new \InvalidArgumentException(sprintf('Workspace "%s" not found', $input->getArgument('workspace')));
        }

        $options = new WorkspaceTemplateOptions(
            withAccessControl: $input->getOption('with-access'),
            withSecrets: $input->getOption('with-secrets'),
        );

        $output->writeln(
            json_encode($this->workspaceTemplater->export($workspace, $options), JSON_PRETTY_PRINT)
        );

        return 0;
    }
}
