<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Core\Workspace;
use App\Service\Workspace\WorkspaceTemplater;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:workspace:import',
    description: 'Import a workspace from a template.',
    help: <<<'HELP'
The template data is read from stdin.
  e.g. <info>sf app:workspace:import new_workspace  --owner 4fe7bbba-0bb3-496b-8d8c-1234567890aa < template.json</info>
If the destination workspace does not exist, it is created (<info>--owner</info> is required); If no <info>--slug</info> is provided, the slugified name is used.
If the destination workspace exists, it is updated with the template data (<info>--force</info> is required; <info>--owner</info> and <info>--slug</info> are ignored).
HELP,
)]
class ImportWorkspaceCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly WorkspaceTemplater $workspaceTemplater,
    ) {
        parent::__construct();
    }

    public function __invoke(
        OutputInterface $output,
        #[Argument('Workspace name to create / import to')]
        string $name,
        #[Option('Workspace slugified name (if creating)', shortcut: 's')]
        ?string $slug = null,
        #[Option('Owner id for of the imported workspace', name: 'owner', shortcut: 'o')]
        ?string $ownerId = null,
        #[Option('Force updating an existing workspace without asking for confirmation', shortcut: 'f')]
        bool $force = false,
    ): int {
        /** @var Workspace $ws */
        $ws = $this->em->getRepository(Workspace::class)->findOneBy(['name' => $name]);
        if ($ws instanceof Workspace) {
            if (!$force) {
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
        if (0010000 !== $mode && 0100000 !== $mode) {
            throw new \InvalidArgumentException('send template to stdin, e.g. `sf app:workspace:import new_workspace < template.json`');
        }
        $data = '';
        while ($s = fread(STDIN, 1024)) {
            $data .= $s;
        }

        $data = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        $this->workspaceTemplater->import($data, $name, $slug, $ownerId);

        return 0;
    }
}
