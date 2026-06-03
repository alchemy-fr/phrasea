<?php

namespace App\Elasticsearch;

use Alchemy\CoreBundle\Cache\TemporaryCacheFactory;
use App\Entity\Core\Asset;
use App\Repository\Core\AssetRepository;
use App\Repository\Core\AttributeRepository;
use Doctrine\ORM\EntityManagerInterface;
use FOS\ElasticaBundle\Persister\ObjectPersisterInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class AssetIndexer
{
    public function __construct(
        #[Autowire(service: 'fos_elastica.object_persister.asset')]
        private ObjectPersisterInterface $assetObjectPersister,
        #[Autowire(service: 'fos_elastica.object_persister.attribute')]
        private ObjectPersisterInterface $attributeObjectPersister,
        private AttributeRepository $attributeRepository,
        private AssetPermissionComputer $assetPermissionComputer,
        private AssetRepository $assetRepository,
        private EntityManagerInterface $em,
        private TemporaryCacheFactory $temporaryCacheFactory,
    ) {
    }

    public function index(
        OutputInterface $output,
        ?string $assetId = null,
        ?string $workspaceId = null,
    ): void {
        $baseQuery = $this->assetRepository->getESQueryBuilder('a');
        if (null !== $assetId) {
            $baseQuery->andWhere('a.id = :assetId');
            $baseQuery->setParameter('assetId', $assetId);
        }
        if (null !== $workspaceId) {
            $baseQuery->andWhere('a.workspace = :workspaceId');
            $baseQuery->setParameter('workspaceId', $workspaceId);
        }

        $total = $baseQuery
            ->select('COUNT(a.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $selectQuery = (clone $baseQuery)
            ->select('a')
            ->resetDQLPart('orderBy')
            ->addOrderBy('a.referenceCollection', 'ASC');

        $progressBar = new ProgressBar($output, $total);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:16s%/%estimated:-16s% %memory:6s%');

        $this->assetPermissionComputer->setWorkspaceCache(new ArrayAdapter(storeSerialized: false));
        $this->assetPermissionComputer->setCollectionCache(new ArrayAdapter(storeSerialized: false));
        $this->assetPermissionComputer->setAssetCache(new ArrayAdapter(storeSerialized: false));

        $maxResults = 500;

        $getPage = function (int $offset) use ($selectQuery, $maxResults): iterable {
            return $selectQuery
                ->getQuery()
                ->setMaxResults($maxResults)
                ->setFirstResult($offset)
                ->toIterable();
        };

        $lastCollectionId = null;
        $progressBar->start();

        $cursor = 0;

        /* @var Asset $asset */
        while ($assets = $getPage($cursor)) {
            $i = 0;

            $shouldClearLastCollection = false;
            $assetStack = [];
            $attributeStack = [];
            foreach ($assets as $asset) {
                if ($lastCollectionId !== $asset->getReferenceCollectionId()) {
                    $shouldClearLastCollection = true;
                    $lastCollectionId = $asset->getReferenceCollectionId();
                }

                $attributes = $this->attributeRepository->getCachedAssetAttributes($asset->getId());
                if (!empty($attributes)) {
                    $attributeStack += $attributes;
                }
                ++$i;
                $assetStack[] = $asset;
            }
            $cursor += $i;

            $this->assetObjectPersister->replaceMany($assetStack);
            $this->attributeObjectPersister->replaceMany($attributeStack);
            unset($assetStack);

            if ($shouldClearLastCollection) {
                $this->assetPermissionComputer->clearCollectionCache();
            }
            $this->assetPermissionComputer->clearAssetCache();
            $this->em->clear();
            $this->temporaryCacheFactory->reset();

            $progressBar->advance($i);

            if ($i < $maxResults) {
                break;
            }
        }

        $progressBar->finish();
    }
}
