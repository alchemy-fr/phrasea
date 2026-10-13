<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInAttribute;

use App\Attribute\Type\AssetStatusAttributeType;
use App\Elasticsearch\AbstractSearch;
use App\Elasticsearch\AQL\ConditionOperatorEnum;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetStatusEnum;
use Elastica\Query;
use Elastica\Query\BoolQuery;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsTaggedItem(index: '@assetStatus')]
final class AssetStatusBuiltInAttribute extends AbstractLabelledBuiltInAttribute implements CustomFilterQueryBuiltInAttributeInterface
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    /**
     * @param int|AssetStatusEnum $value
     */
    #[\Override]
    public function resolveLabel($value): string
    {
        return (AssetStatusEnum::tryFrom($value) ?? AssetStatusEnum::Accepted)->name
                |> strtolower(...)
                |> (fn ($x) => sprintf('asset_status.%s', $x))
                |> $this->translator->trans(...);
    }

    #[\Override]
    public function getType(): string
    {
        return AssetStatusAttributeType::NAME;
    }

    #[\Override]
    protected function resolveKey($value): string
    {
        return (string) $value;
    }

    public static function getName(): string
    {
        return 'assetStatus';
    }

    public static function getKey(): string
    {
        return '@assetStatus';
    }

    public function getValueFromAsset(Asset $asset): mixed
    {
        return $asset->getStatus();
    }

    protected function getAggregationTranslationKey(): string
    {
        return 'assetStatus';
    }

    public function createFilterQuery(mixed $value, ConditionOperatorEnum $operator, array $options): Query\AbstractQuery
    {
        $boolQuery = new BoolQuery();

        $filterQuarantine = function (bool $containsQuarantine) use ($boolQuery, $options): void {
            if ($containsQuarantine) {
                $boolQuery->addMust($this->createQuarantineAccessQuery($options));
            }
        };

        switch ($operator) {
            case ConditionOperatorEnum::EQUALS:
                $value = AssetStatusAttributeType::normalizeInput($value);
                $boolQuery->addMust(new Query\Term(['status' => $value?->value]));
                $filterQuarantine(in_array($value, [AssetStatusEnum::Quarantined, AssetStatusEnum::Pending]));
                break;
            case ConditionOperatorEnum::NOT_EQUALS:
                $value = AssetStatusAttributeType::normalizeInput($value);
                $boolQuery->addMustNot(new Query\Term(['status' => $value?->value]));
                $filterQuarantine(in_array($value, [AssetStatusEnum::Accepted]));
                break;
            case ConditionOperatorEnum::IN:
                $boolQuery->setMinimumShouldMatch(1);
                foreach ($value as $item) {
                    $boolQuery->addShould($this->createFilterQuery($item, ConditionOperatorEnum::EQUALS, $options));
                }
                break;
            case ConditionOperatorEnum::NOT_IN:
                $boolQuery->setMinimumShouldMatch(1);
                foreach ($value as $item) {
                    $boolQuery->addMustNot($this->createFilterQuery($item, ConditionOperatorEnum::EQUALS, $options));
                }
                break;
            default:
                throw new \InvalidArgumentException(sprintf('Operator %s is not supported for assetStatus field', $operator->value));
        }

        return $boolQuery;
    }

    /**
     * The assets the user may see whatever their status: the accepted ones, and the others
     * when the user has access to the quarantine.
     */
    public function createVisibleFilterQuery(array $options): Query\AbstractQuery
    {
        $boolQuery = new BoolQuery();
        $boolQuery->setMinimumShouldMatch(1);
        $boolQuery->addShould(new Query\Term(['status' => AssetStatusEnum::Accepted->value]));
        $boolQuery->addShould($this->createQuarantineAccessQuery($options));

        return $boolQuery;
    }

    private function createQuarantineAccessQuery(array $options): Query\AbstractQuery
    {
        $userId = $options['userId'] ?? null;
        if (empty($userId)) {
            return new Query\Term(['ownerId' => AbstractSearch::NO_AUTH]);
        }

        $boolQuery = new BoolQuery();
        $boolQuery->setMinimumShouldMatch(1);
        $boolQuery->addShould(new Query\Term(['ownerId' => $userId]));
        $boolQuery->addShould(new Query\Term(['quarantineUsers' => $userId]));
        $groups = $options['groupIds'] ?? null;
        if (!empty($groups)) {
            $boolQuery->addShould(new Query\Terms('quarantineGroups', $groups));
        }

        return $boolQuery;
    }

    #[\Override]
    protected function getAggregationSize(): int
    {
        return count(AssetStatusEnum::cases());
    }

    public function isFacet(): bool
    {
        return false;
    }
}
