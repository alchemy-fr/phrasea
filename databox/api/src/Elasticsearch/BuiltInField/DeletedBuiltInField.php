<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInField;

use App\Attribute\Type\BooleanAttributeType;
use App\Elasticsearch\AbstractSearch;
use App\Elasticsearch\AQL\ConditionOperatorEnum;
use App\Entity\Core\Asset;
use Elastica\Query;

final class DeletedBuiltInField extends AbstractBuiltInAttribute implements CustomFilterQueryBuiltInAttributeInterface
{
    protected function getAggregationTranslationKey(): string
    {
        return 'deleted';
    }

    public static function getName(): string
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

    #[\Override]
    public function getType(): string
    {
        return BooleanAttributeType::getName();
    }

    public function createFilterQuery(mixed $value, ConditionOperatorEnum $operator, array $options): Query\AbstractQuery
    {
        $boolQuery = new Query\BoolQuery();

        switch ($operator) {
            case ConditionOperatorEnum::EQUALS:
                return $this->createEqualFilterQuery($value, $options);
            case ConditionOperatorEnum::NOT_EQUALS:
                if (null !== $value) {
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                }

                return $this->createEqualFilterQuery(!$value, $options);
            case ConditionOperatorEnum::IN:
                $boolQuery->setMinimumShouldMatch(1);
                foreach ($value as $item) {
                    $boolQuery->addShould($this->createEqualFilterQuery($item, $options));
                }
                break;
            case ConditionOperatorEnum::NOT_IN:
                $boolQuery->setMinimumShouldMatch(1);
                foreach ($value as $item) {
                    $boolQuery->addMustNot($this->createEqualFilterQuery($item, $options));
                }
                break;
            default:
                throw new \InvalidArgumentException(sprintf('Operator %s is not supported for assetStatus field', $operator->value));
        }

        return $boolQuery;
    }

    public function createEqualFilterQuery(mixed $value, array $options): Query\BoolQuery
    {
        if (null !== $value) {
            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        if (false === $value) {
            return $this->createIsNotDeletedQuery();
        }

        $userId = $options['userId'] ?? null;
        $groupIds = $options['groupIds'] ?? [];

        $deletePermissionQuery = $this->createDeletePermissionQuery($userId, $groupIds);

        $boolQuery = new Query\BoolQuery();
        if (null === $value) {
            $boolQuery->addShould($this->createIsNotDeletedQuery());
            $boolQuery->addShould($deletePermissionQuery);
        } else {
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
        $deletePermissionQuery->setMinimumShouldMatch(1);

        if (null !== $userId) {
            $deletePermissionQuery->addShould(new Query\Term(['ownerId' => $userId]));
            $deletePermissionQuery->addShould(new Query\Term(['deleteUsers' => $userId]));
        } else {
            $deletePermissionQuery->addShould(new Query\Term(['ownerId' => AbstractSearch::NO_AUTH]));
        }
        if (!empty($groupIds)) {
            $deletePermissionQuery->addShould(new Query\Terms('deleteGroups', $groupIds));
        }

        return $deletePermissionQuery;
    }

    private function createIsNotDeletedQuery(): Query\BoolQuery
    {
        $isNotDeletedQuery = new Query\BoolQuery();
        $isNotDeletedQuery->addMust(new Query\Term(['deleted' => false]));
        $isNotDeletedQuery->addMust(new Query\Term(['collectionDeleted' => false]));

        return $isNotDeletedQuery;
    }

    #[\Override]
    public function isFacet(): bool
    {
        return false;
    }
}
