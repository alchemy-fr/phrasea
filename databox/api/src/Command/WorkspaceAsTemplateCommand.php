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

#[AsCommand('app:workspace:template')]
class WorkspaceAsTemplateCommand extends Command
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
            ->setDescription('Export or import a workspace as a template')
            ->addOption('export', null, InputOption::VALUE_REQUIRED, 'Workspace id to export')
            ->addOption('import', null, InputOption::VALUE_REQUIRED, 'Workspace name to import into')
            ->addOption('owner', null, InputOption::VALUE_REQUIRED, 'Owner id for of the imported workspace')
            ->setHelp(<<<'HELP'
Export a workspace as a template, or import a workspace from a template.

- When only exporting:
  The template is written on stdout.
  e.g. <info>app:workspace:template --export cebf0a08-f334-4369-aea6-1234567890aa > template.json</info>
- When only importing:
  The template data is read from stdin, and a owner id must be provided.
  e.g. <info>sf app:workspace:template --import new_workspace  --owner 4fe7bbba-0bb3-496b-8d8c-1234567890aa < template.json</info>
- When both exporting and importing:
  No template is generated, and - if not provided with --owner -, the source owner is used on destination.
  e.g. <info>sf app:workspace:template --export cebf0a08-f334-4369-aea6-1234567890aa --import new_workspace</info>
  One can provide a owner id, to change the owner of the destination.

If the destination workspace already exists on --import, imported settings will override existing ones.
HELP
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $data = false;
        $ownerId = null;
        if (null !== ($workspaceId = $input->getOption('export'))) {
            /** @var Workspace $workspace */
            $workspace = $this->em->find(Workspace::class, $workspaceId);
            if (!$workspace instanceof Workspace) {
                throw new \InvalidArgumentException(sprintf('Workspace "%s" not found', $workspaceId));
            }

            $data = $this->workspaceTemplater->export($workspace);
            $ownerId = $workspace->getOwnerId();

            if (!$input->getOption('import')) {
                $output->writeln($data);
            }
        }
        if ($input->getOption('import')) {
            $output->writeln('Importing workspace');
            if ($input->getOption('owner')) {
                $ownerId = $input->getOption('owner');
            }
            if (!$data) {
                if (!$ownerId) {
                    throw new \InvalidArgumentException('Owner id is required for import');
                }
                $data = '';
                while ($s = fread(STDIN, 1024)) {
                    $data .= $s;
                }
            }
            $this->workspaceTemplater->import($data, $input->getOption('import'), $ownerId);
        }

        return 0;
    }
}
