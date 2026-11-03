<?php

declare(strict_types=1);

namespace App\Api\Traits;

use ApiPlatform\Metadata\Operation;
use App\Api\Provider\PagerFantaApiPlatformPaginator;
use Doctrine\ORM\QueryBuilder;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;

/**
 * Paginates a Doctrine query with the "page" and "limit" query parameters of the operation.
 */
trait QueryPaginationTrait
{
    use ParameterValuesTrait;

    protected static function paginateQuery(Operation $operation, QueryBuilder $queryBuilder, int $defaultLimit = 30, int $maxLimit = 100): PagerFantaApiPlatformPaginator
    {
        $limit = (int) self::getParameterValue($operation, 'limit', $defaultLimit);
        if ($limit <= 0) {
            $limit = $defaultLimit;
        }

        $pager = new Pagerfanta(new QueryAdapter($queryBuilder, false, false));
        $pager->setMaxPerPage(min($limit, $maxLimit));
        $pager->setAllowOutOfRangePages(true);
        $pager->setCurrentPage(max(1, (int) self::getParameterValue($operation, 'page', 1)));

        return new PagerFantaApiPlatformPaginator($pager);
    }
}
