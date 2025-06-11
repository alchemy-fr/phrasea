<?php

namespace App\Elasticsearch;

use App\Entity\Core\Asset;
use App\Repository\Core\AssetRepository;
use App\Repository\Core\AttributeRepository;
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
    ) {
    }

    public function index(OutputInterface $output): void
    {
        $total = $this->assetRepository->getESQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();
        $progressBar = new ProgressBar($output, $total);
        $assets = $this->assetRepository->getESQueryBuilder('a')
            ->select('a')
            ->resetDQLPart('orderBy')
            ->addOrderBy('a.referenceCollection', 'ASC')
            ->getQuery()
            ->toIterable();

        $this->assetPermissionComputer->setCollectionCache(new ArrayAdapter());
        $this->assetPermissionComputer->setAssetCache(new ArrayAdapter());

        $lastCollectionId = null;
        $progressBar->start();
        /** @var Asset $asset */
        foreach ($assets as $asset) {
            if ($lastCollectionId !== $asset->getReferenceCollectionId()) {
                $this->assetPermissionComputer->clearCollectionCache();
                $lastCollectionId = $asset->getReferenceCollectionId();
            }
            $this->assetPermissionComputer->clearAssetCache();

            $attributes = $this->attributeRepository->getCachedAssetAttributes($asset->getId());
            $this->assetObjectPersister->replaceMany([$asset]);
            if (!empty($attributes)) {
                $this->attributeObjectPersister->replaceMany($attributes);
            }

            $progressBar->advance();
        }

        $progressBar->finish();
    }
}
