<?php

declare(strict_types=1);

namespace App\Elasticsearch\Mapping;

use App\Entity\Admin\ESIndexState;
use Doctrine\ORM\EntityManagerInterface;
use FOS\ElasticaBundle\Configuration\ManagerInterface;
use FOS\ElasticaBundle\Index\MappingBuilder;

readonly class IndexSyncState
{
    public function __construct(
        private EntityManagerInterface $em,
        private ManagerInterface $configManager,
        private MappingBuilder $mappingBuilder,
        private IndexMappingDiff $mappingDiff
    ) {
    }

    public function snapshotStateMapping(string $indexName): void
    {
        /** @var ESIndexState|null $state */
        $state = $this->em->getRepository(ESIndexState::class)->findOneBy([
            'indexName' => $indexName,
        ]);

        if (null === $state) {
            $state = new ESIndexState();
            $state->setIndexName($indexName);
        }

        $state->setMapping($this->getCurrentConfigMapping($indexName));
        $this->em->persist($state);
    }

    public function getStateMapping(string $indexName): ?array
    {
        /** @var ESIndexState|null $state */
        $state = $this->em->getRepository(ESIndexState::class)->findOneBy([
            'indexName' => $indexName,
        ]);

        return $state?->getMapping();

    }

    public function getCurrentConfigMapping(string $indexName): array
    {
        $indexConfig = $this->configManager->getIndexConfiguration($indexName);

        return $this->mappingBuilder->buildIndexMapping($indexConfig);
    }

    public function shouldReindex(string $indexName): ?bool
    {
        $stateMapping = $this->getStateMapping($indexName);
        if (null === $stateMapping) {
            return null;
        }

        $expectedMapping = $this->getCurrentConfigMapping($indexName);

        return $this->mappingDiff->shouldReindex($stateMapping, $expectedMapping);
    }
}
