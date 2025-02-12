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
use Symfony\Component\Console\Question\ConfirmationQuestion;

#[AsCommand('app:workspace:import')]
class ImportWorkspaceCommand extends Command
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
            ->setDescription('Import a workspace from a template.')
            ->addArgument('name', InputOption::VALUE_REQUIRED, 'Workspace name to create / import to')
            ->addOption('slug', 's', InputOption::VALUE_REQUIRED, 'Workspace slugified name (if creating)')
            ->addOption('owner', 'o', InputOption::VALUE_REQUIRED, 'Owner id for of the imported workspace')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force updating an existing workspace without asking for confirmation')

            ->setHelp(<<<'HELP'
The template data is read from stdin.
  e.g. <info>sf app:workspace:template new_workspace  --owner 4fe7bbba-0bb3-496b-8d8c-1234567890aa < template.json</info>
If the destination workspace does not exist, it is created (<info>--owner</info> is required); Is no <info>--slug</info> is provided, the slugified name is used.
If the destination workspace exists, it is updated with the template data (<info>--force</info> is required; <info>--owner</info> and <info>--slug</info> are ignored).
HELP
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $ownerId = $input->getOption('owner');
        $slug = $input->getOption('slug');

        /** @var Workspace $ws */
        $ws = $this->em->getRepository(Workspace::class)->findOneBy(['name' => $name]);
        if ($ws instanceof Workspace) {
            if(!$input->getOption('force')) {
                throw new \InvalidArgumentException('Workspace already exists, use --force to update it.');
            }
            $ownerId = $slug = null;
            $output->writeln('Updating Workspace.');
        } else {
            if (!$ownerId) {
                throw new \InvalidArgumentException('--owner is required for creating a new workspace');
            }
            $output->writeln('Creating Workspace.');
        }

        $stat = fstat(STDIN);
        $mode = $stat['mode'] & 0170000; // S_IFMT
        if ($mode !== 0010000 && $mode !== 0100000) {
            throw new \InvalidArgumentException(
                'send template to stdin, e.g. `sf app:workspace:import new_workspace < template.json`'
            );
        }
        $data = '';
        while ($s = fread(STDIN, 1024)) {
            $data .= $s;
        }

        $this->workspaceTemplater->import($data, $name, $slug, $ownerId);

        return 0;
    }
}
