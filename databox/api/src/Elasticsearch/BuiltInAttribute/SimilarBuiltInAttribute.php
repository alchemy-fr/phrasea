<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInAttribute;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use App\Attribute\Type\KeywordAttributeType;
use App\Elasticsearch\AQL\ConditionOperatorEnum;
use App\Elasticsearch\SimilarAssetSearch;
use App\Entity\Core\Asset;
use App\Repository\Core\AssetRepository;
use App\Security\Voter\AbstractVoter;
use Elastica\Query;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class SimilarBuiltInAttribute extends AbstractBuiltInAttribute implements CustomFilterQueryBuiltInAttributeInterface
{
    use SecurityAwareTrait;

    private const int NUM_CANDIDATES = 500;

    public function __construct(
        private readonly AssetRepository $assetRepository,
        private readonly SimilarAssetSearch $similarAssetSearch,
    ) {
    }

    protected function getAggregationTranslationKey(): string
    {
        return 'similar';
    }

    public static function getName(): string
    {
        return 'similar';
    }

    public static function getKey(): string
    {
        return '@similar';
    }

    public function getValueFromAsset(Asset $asset): mixed
    {
        return null;
    }

    #[\Override]
    public function getType(): string
    {
        return KeywordAttributeType::NAME;
    }

    #[\Override]
    public function isFacet(): bool
    {
        return false;
    }

    #[\Override]
    public function isSortable(): bool
    {
        return false;
    }

    public function isScoreBasedQuery(): bool
    {
        return true;
    }

    public function createFilterQuery(mixed $value, ConditionOperatorEnum $operator, array $options): Query\AbstractQuery
    {
        if (ConditionOperatorEnum::EQUALS !== $operator) {
            throw new BadRequestHttpException(sprintf('Operator "%s" is not supported for %s field', $operator->value, self::getKey()));
        }

        if (!is_string($value) || '' === $value) {
            throw new BadRequestHttpException(sprintf('%s expects an asset ID', self::getKey()));
        }

        $asset = $this->assetRepository->find($value);
        if (!$asset instanceof Asset || !$this->isGranted(AbstractVoter::READ, $asset)) {
            return new Query\MatchNone();
        }

        $knn = $this->similarAssetSearch->createKnnQuery(
            $options['userId'] ?? null,
            $options['groupIds'] ?? [],
            $asset,
            self::NUM_CANDIDATES,
        );

        return $knn ?? new Query\MatchNone();
    }
}
