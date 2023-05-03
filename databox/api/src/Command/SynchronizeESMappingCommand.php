<?php

declare(strict_types=1);

namespace App\Command;

use App\Elasticsearch\Mapping\IndexMappingUpdater;
use App\Entity\Core\Workspace;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SynchronizeESMappingCommand extends Command
{
    public function __construct(private readonly IndexMappingUpdater $indexMappingUpdater, private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('app:search:sync-mapping')
            ->setDescription('Synchronize Elasticsearch mapping with attribute definitions')
            ->addArgument('workspace', InputArgument::REQUIRED)
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $workspaceId = $input->getArgument('workspace');
        $workspace = $this->em->find(Workspace::class, $workspaceId);

        if (!$workspace instanceof Workspace) {
            throw new \InvalidArgumentException('Workspace '.$workspaceId.' not found');
        }

        $this->indexMappingUpdater->synchronizeWorkspace($workspace);

        $output->writeln('Done.');

        return 0;
    }
}
