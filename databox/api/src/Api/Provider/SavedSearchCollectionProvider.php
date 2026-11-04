<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use App\Api\Traits\ParameterValuesTrait;
use App\Entity\SavedSearch\SavedSearch;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;

class SavedSearchCollectionProvider extends AbstractCollectionProvider
{
    use SecurityAwareTrait;
    use ParameterValuesTrait;

    private const int DEFAULT_LIMIT = 30;
    private const int MAX_LIMIT = 100;

    protected function provideCollection(
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): array|object {
        $user = $this->security->getUser();
        $userId = $user instanceof JwtUser ? $user->getId() : null;
        $groupIds = $user instanceof JwtUser ? $user->getGroups() : [];

        $queryBuilder = $this->em->getRepository(SavedSearch::class)
            ->createQueryBuilderAcl($userId, $groupIds)
            // A saved search granted by several ACEs (user and group) is joined once per ACE
            ->addGroupBy('t.id')
            ->addOrderBy('t.name', 'ASC')
            ->addOrderBy('t.createdAt', 'DESC')
            ->addOrderBy('t.id', 'ASC')
        ;

        $queryString = self::getParameterValue($operation, 'query');
        if (\is_string($queryString) && '' !== trim($queryString)) {
            $queryBuilder
                ->andWhere('LOWER(t.name) LIKE LOWER(:query) ESCAPE \'\\\'')
                ->setParameter('query', '%'.addcslashes(trim($queryString), '\\%_').'%');
        }

        $limit = (int) self::getParameterValue($operation, 'limit', self::DEFAULT_LIMIT);
        if ($limit <= 0) {
            $limit = self::DEFAULT_LIMIT;
        }

        $pager = new Pagerfanta(new QueryAdapter($queryBuilder, false, false));
        $pager->setMaxPerPage(min($limit, self::MAX_LIMIT));
        $pager->setAllowOutOfRangePages(true);
        $pager->setCurrentPage(max(1, (int) self::getParameterValue($operation, 'page', 1)));

        return new PagerFantaApiPlatformPaginator($pager);
    }
}
