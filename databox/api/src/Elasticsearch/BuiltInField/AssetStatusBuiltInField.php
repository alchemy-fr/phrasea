<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInField;

use App\Attribute\Type\AssetStatusAttributeType;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetStatusEnum;
use Elastica\Query;
use Symfony\Contracts\Translation\TranslatorInterface;

final class AssetStatusBuiltInField extends AbstractLabelledBuiltInField
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

    public function createFilterQuery(mixed $value, array $options): Query\AbstractQuery
    {
        $boolQuery = new Query\BoolQuery();
        $boolQuery->addMust(new Query\Term(['status' => $value]));

        $value = AssetStatusAttributeType::normalizeInput($value);

        switch ($value) {
            case AssetStatusEnum::Quarantined:
            case AssetStatusEnum::Pending:
                $boolQuery->addMust(new Query\Term(['ownerId' => $options['userId'] ?? 'nobody']));
                break;
            case AssetStatusEnum::Accepted:
                break;
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
