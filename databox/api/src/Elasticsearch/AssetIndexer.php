<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use Alchemy\CoreBundle\Cache\TemporaryCacheFactory;
use App\Entity\Core\Asset;
use App\OperationTask\RunContext;
use App\Repository\Core\AssetRepository;
use App\Service\Asset\Attribute\AttributeValueResolver;
use Doctrine\ORM\EntityManagerInterface;
use FOS\ElasticaBundle\Persister\ObjectPersisterInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class AssetIndexer
{
    private const int PAGE_SIZE = 100;

    public function __construct(
        #[Autowire(service: 'fos_elastica.object_persister.asset')]
        private ObjectPersisterInterface $assetObjectPersister,
        private AssetPermissionComputer $assetPermissionComputer,
        private AssetRepository $assetRepository,
        private EntityManagerInterface $em,
        private TemporaryCacheFactory $temporaryCacheFactory,
        private AssetIndexPreloader $preloader,
        private AttributeValueResolver $attributeValueResolver,
    ) {
    }

    public function index(
        RunContext $runContext,
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

        if (0 === $total) {
            return;
        }

        $selectQuery = (clone $baseQuery)
            ->select('a')
            ->resetDQLPart('orderBy')
            ->addOrderBy('a.referenceCollection', 'ASC')
            ->addOrderBy('a.createdAt', 'DESC')
            ->addOrderBy('a.id', 'ASC');

        $this->assetPermissionComputer->setWorkspaceCache(new ArrayAdapter(deepClone: false));
        $this->assetPermissionComputer->setCollectionCache(new ArrayAdapter(deepClone: false));
        $this->assetPermissionComputer->setAssetCache(new ArrayAdapter(deepClone: false));

        try {
            $getPage = (fn (int $offset): iterable => $selectQuery
                ->getQuery()
                ->setMaxResults(self::PAGE_SIZE)
                ->setFirstResult($offset)
                ->toIterable());

            $lastCollectionId = null;
            $runContext->start((int) $total);
            $runContext->getProgressBar()->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:16s%/%estimated:-16s% %memory:6s%');

            $cursor = 0;

            /* @var Asset $asset */
            while ($assets = $getPage($cursor)) {
                $i = 0;

                $shouldClearLastCollection = false;
                $assetStack = [];
                foreach ($assets as $asset) {
                    if ($lastCollectionId !== $asset->getReferenceCollectionId()) {
                        $shouldClearLastCollection = true;
                        $lastCollectionId = $asset->getReferenceCollectionId();
                    }

                    ++$i;
                    $assetStack[] = $asset;
                }
                $cursor += $i;

                if (!empty($assetStack)) {
                    $this->preloader->preload($assetStack);
                    $this->assetObjectPersister->replaceMany($assetStack);
                }
                unset($assetStack);

                if ($shouldClearLastCollection) {
                    $this->assetPermissionComputer->clearCollectionCache();
                }
                $this->assetPermissionComputer->clearAssetCache();
                $this->em->clear();
                $this->temporaryCacheFactory->reset();
                $this->preloader->reset();
                $this->attributeValueResolver->reset();
                // The cleared entities form cycles that only the cycle collector frees
                gc_collect_cycles();

                $runContext->advance($i);

                if ($i < self::PAGE_SIZE) {
                    break;
                }
            }
        } finally {
            // The worker keeps these services between messages: what runs afterwards (a populate, the real-time indexing) must not reuse these caches nor the preloaded values
            $this->preloader->reset();
            $this->assetPermissionComputer->disableWorkspaceCache();
            $this->assetPermissionComputer->disableCollectionCache();
            $this->assetPermissionComputer->disableAssetCache();
        }

        $runContext->finish();
    }
}
