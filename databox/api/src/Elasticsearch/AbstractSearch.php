<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use Elastica\Query;

abstract class AbstractSearch
{
    public function createACLBoolQuery(?string $userId, array $groupIds): Query\BoolQuery
    {
        $aclBoolQuery = new Query\BoolQuery();

        $shoulds = [
            new Query\Term(['public' => true]),
        ];

        if (null !== $userId) {
            $shoulds[] = new Query\Term(['ownerId' => $userId]);
            $shoulds[] = new Query\Term(['users' => $userId]);
            $shoulds[] = new Query\Terms('groups', $groupIds);
        }

        foreach ($shoulds as $query) {
            $aclBoolQuery->addShould($query);
        }

        return $aclBoolQuery;
    }
}
