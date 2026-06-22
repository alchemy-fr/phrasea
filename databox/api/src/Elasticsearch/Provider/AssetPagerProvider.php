<?php

declare(strict_types=1);

namespace App\Elasticsearch\Provider;

use App\Repository\Core\AssetRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use FOS\ElasticaBundle\Doctrine\RegisterListenersService;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AutoconfigureTag('fos_elastica.pager_provider', attributes: ['index' => 'asset'])]
final readonly class AssetPagerProvider extends AbstractPagerProvider
{
    public function __construct(
        private AssetRepository $repository,
        ManagerRegistry $doctrine,
        #[Autowire(service: 'fos_elastica.doctrine.register_listeners')]
        RegisterListenersService $registerListenersService,
    ) {
        parent::__construct(
            $doctrine,
            $registerListenersService,
        );
    }

    protected function getQueryBuilder(): QueryBuilder
    {
        return $this->repository->getESQueryBuilder();
    }
}
