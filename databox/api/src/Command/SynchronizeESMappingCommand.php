<?php

declare(strict_types=1);

namespace App\Command;

use App\Elasticsearch\Mapping\IndexMappingUpdater;
use App\Entity\Core\Workspace;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\ResultSetMapping;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SynchronizeESMappingCommand extends Command
{
    public function __construct(
        private readonly IndexMappingUpdater $indexMappingUpdater,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('app:search:sync-mapping')
            ->setDescription('Synchronize Elasticsearch mapping with attribute definitions')
            ->addArgument('workspace', InputArgument::REQUIRED)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $q = $this->em->createNativeQuery('
        SELECT COUNT(*) AS dctrn_count FROM (SELECT DISTINCT id_0 FROM (SELECT a0_.id AS id_0, a0_.locale AS locale_1, a0_.position AS position_2, a0_.value AS value_3, a0_.created_at AS created_at_4, a0_.updated_at AS updated_at_5, a0_.locked AS locked_6, a0_.translation_id AS translation_id_7, a0_.translation_origin_hash AS translation_origin_hash_8, a0_.origin AS origin_9, a0_.origin_vendor AS origin_vendor_10, a0_.origin_user_id AS origin_user_id_11, a0_.origin_vendor_context AS origin_vendor_context_12, a0_.coordinates AS coordinates_13, a0_.status AS status_14, a0_.confidence AS confidence_15 FROM attribute a0_ INNER JOIN attribute_definition a1_ ON a0_.definition_id = a1_.id WHERE a1_.field_type IN (1,2) ORDER BY a0_.asset_id ASC, a0_.id ASC) dctrn_result) dctrn_table
        ', new ResultSetMapping());
        $q->getResult();

        throw new \InvalidArgumentException(sprintf('OK'));

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
