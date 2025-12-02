<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInField;

use App\Attribute\Type\BooleanAttributeType;
use App\Entity\Core\Asset;
use Elastica\Query;

final class DeletedBuiltInField extends AbstractBuiltInField
{
    protected function getAggregationTranslationKey(): string
    {
        return 'deleted';
    }

    public function getFieldName(): string
    {
        return 'deleted';
    }

    public static function getKey(): string
    {
        return '@deleted';
    }

    public function getValueFromAsset(Asset $asset): mixed
    {
        return $asset->isDeleted();
    }

    public function getType(): string
    {
        return BooleanAttributeType::getName();
    }

    public function createFilterQuery(mixed $value, array $options): ?Query\AbstractQuery
    {
        if (null !== $value) {
            $value = (bool) filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        if (false === $value) {
            return $this->createIsNotDeletedQuery();
        }

        $userId = $options['userId'] ?? null;
        $groupIds = $options['groupIds'] ?? [];

        $boolQuery = new Query\BoolQuery();
        if (null === $value) {
            $boolQuery->addShould($this->createIsNotDeletedQuery());
            $deletePermissionQuery = $this->createDeletePermissionQuery($userId, $groupIds);
            $boolQuery->addShould($deletePermissionQuery);
        } else {
            $deletePermissionQuery = $this->createDeletePermissionQuery($userId, $groupIds);
            $boolQuery->addMust($deletePermissionQuery);
            $deletedQuery = new Query\BoolQuery();
            $deletedQuery->addShould(new Query\Term(['deleted' => true]));
            $deletedQuery->addShould(new Query\Term(['collectionDeleted' => true]));
            $boolQuery->addMust($deletedQuery);
        }

        return $boolQuery;
    }

    private function createDeletePermissionQuery(?string $userId, array $groupIds): Query\BoolQuery
    {
        $deletePermissionQuery = new Query\BoolQuery();

        if (null !== $userId) {
            $deletePermissionQuery->addShould(new Query\Term(['ownerId' => $userId]));
            $deletePermissionQuery->addShould(new Query\Term(['deleteUsers' => $userId]));
        }
        if (!empty($groupIds)) {
            $deletePermissionQuery->addShould(new Query\Terms('deleteGroups', $groupIds));
        }

        $deletePermissionQuery->setMinimumShouldMatch(1);

        return $deletePermissionQuery;
    }

    private function createIsNotDeletedQuery(): Query\BoolQuery
    {
        $isNotDeletedQuery = new Query\BoolQuery();
        $isNotDeletedQuery->addMust(new Query\Term(['deleted' => false]));
        $isNotDeletedQuery->addMust(new Query\Term(['collectionDeleted' => false]));

        return $isNotDeletedQuery;
    }

    public function isFacet(): bool
    {
        return false;
    }
}
