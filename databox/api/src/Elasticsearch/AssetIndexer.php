<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use Alchemy\CoreBundle\Cache\TemporaryCacheFactory;
use App\Entity\Core\Asset;
use App\OperationTask\RunContext;
use App\Repository\Core\AssetRepository;
use App\Repository\Core\AttributeRepository;
use Doctrine\ORM\EntityManagerInterface;
use FOS\ElasticaBundle\Persister\ObjectPersisterInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
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

        $this->assetPermissionComputer->setWorkspaceCache(new ArrayAdapter(storeSerialized: false));
        $this->assetPermissionComputer->setCollectionCache(new ArrayAdapter(storeSerialized: false));
        $this->assetPermissionComputer->setAssetCache(new ArrayAdapter(storeSerialized: false));

        $maxResults = 500;

        $getPage = (fn (int $offset): iterable => $selectQuery
            ->getQuery()
            ->setMaxResults($maxResults)
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

            if (!empty($assetStack)) {
                $this->assetObjectPersister->replaceMany($assetStack);
            }
            if (!empty($attributeStack)) {
                $this->attributeObjectPersister->replaceMany($attributeStack);
            }
            unset($assetStack);

            if ($shouldClearLastCollection) {
                $this->assetPermissionComputer->clearCollectionCache();
            }
            $this->assetPermissionComputer->clearAssetCache();
            $this->em->clear();
            $this->temporaryCacheFactory->reset();

            $runContext->advance($i);

            if ($i < $maxResults) {
                break;
            }
        }

        $runContext->finish();
    }
}
