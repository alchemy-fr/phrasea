<?php

declare(strict_types=1);

namespace App\Elasticsearch\Provider;

use App\Entity\Core\Attribute;
use Doctrine\ORM\Query\Expr\From;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use FOS\ElasticaBundle\Doctrine\RegisterListenersService;
use FOS\ElasticaBundle\Provider\PagerfantaPager;
use FOS\ElasticaBundle\Provider\PagerInterface;
use FOS\ElasticaBundle\Provider\PagerProviderInterface;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

// Replace \FOS\ElasticaBundle\Doctrine\ORMPagerProvider to set $fetchJoinCollection to false
abstract readonly class AbstractPagerProvider implements PagerProviderInterface
{
    public function __construct(
        private ManagerRegistry $doctrine,
        #[Autowire(service: 'fos_elastica.doctrine.register_listeners')]
        private RegisterListenersService $registerListenersService,
    ) {
    }

    abstract protected function getQueryBuilder(): QueryBuilder;

    public function provide(array $options = []): PagerInterface
    {
        $manager = $this->doctrine->getManagerForClass(Attribute::class);

        $qb = $this->getQueryBuilder();

        if (empty($qb->getDQLPart('orderBy'))) {
            // When getting root aliases, the QueryBuilder normalizes all from parts to From objects, in case they were added as string using the low-level API.
            // This side-effect allows us to be sure to get only From objects in the next call.
            $qb->getRootAliases();

            /** @var From[] $fromClauses */
            $fromClauses = $qb->getDQLPart('from');

            foreach ($fromClauses as $fromClause) {
                $identifiers = $manager->getClassMetadata($fromClause->getFrom())->getIdentifierFieldNames();

                foreach ($identifiers as $identifier) {
                    $qb->addOrderBy($fromClause->getAlias().'.'.$identifier);
                }
            }
        }

        $pager = new PagerfantaPager(new Pagerfanta(new QueryAdapter($qb, false, false)));

        $this->registerListenersService->register($manager, $pager, $options);

        return $pager;
    }
}
