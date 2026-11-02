<?php

declare(strict_types=1);

namespace App\Service\Vector;

use App\Elasticsearch\AQL\AQLParser;
use App\Elasticsearch\AQL\ConditionOperatorEnum;
use App\Elasticsearch\BuiltInAttribute\SimilarBuiltInAttribute;
use App\Entity\Core\AssetEmbedding;
use App\Integration\Core\FaceRecognition\FaceMatcher;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Similarity of assets to the reference asset of a `@similar` search, on the scale of the
 * Elasticsearch kNN score of a cosine field: (1 + cosine) / 2.
 *
 * The score of a search hit cannot be used: it adds up the full-text relevance and the
 * similarity as soon as the search also has a query.
 */
final readonly class AssetSimilarityScorer
{
    public function __construct(
        private EntityManagerInterface $em,
        private AQLParser $AQLParser,
    ) {
    }

    /**
     * The asset the conditions rank the results by similarity to: the value of their single
     * `@similar = "<id>"` condition. Null when there is none, or when it does not rank the
     * results alone (inverted, combined with another expression, several references).
     *
     * @param array<string|array{query?: string, inversed?: bool, disabled?: bool}> $conditions
     */
    public function getReferenceAssetId(array $conditions): ?string
    {
        $referenceIds = [];
        foreach ($conditions as $condition) {
            if (is_array($condition)) {
                if (($condition['disabled'] ?? false) || ($condition['inversed'] ?? false)) {
                    continue;
                }
                $condition = $condition['query'] ?? '';
            }
            if (!is_string($condition) || !str_contains($condition, SimilarBuiltInAttribute::getKey())) {
                continue;
            }

            $data = $this->AQLParser->parse($condition)['data'] ?? null;
            if ('criteria' !== ($data['type'] ?? null)
                || SimilarBuiltInAttribute::getKey() !== ($data['leftOperand']['field'] ?? null)
                || ConditionOperatorEnum::EQUALS->value !== ($data['operator'] ?? null)
            ) {
                return null;
            }

            $value = $data['rightOperand']['literal'] ?? null;
            if (!is_string($value) || '' === $value) {
                return null;
            }
            $referenceIds[$value] = true;
        }

        return 1 === count($referenceIds) ? array_key_first($referenceIds) : null;
    }

    /**
     * @param string[] $assetIds
     *
     * @return array<string, float> the score of each asset having an embedding, by asset ID
     */
    public function score(string $referenceAssetId, array $assetIds): array
    {
        if (empty($assetIds)) {
            return [];
        }

        $rows = $this->em->createQueryBuilder()
            ->select('IDENTITY(e.asset) AS assetId, e.vector')
            ->from(AssetEmbedding::class, 'e')
            ->andWhere('e.asset IN (:ids)')
            ->setParameter('ids', array_values(array_unique([$referenceAssetId, ...$assetIds])))
            ->getQuery()
            ->getArrayResult();

        $vectors = [];
        foreach ($rows as $row) {
            $vectors[(string) $row['assetId']] = $row['vector'];
        }

        $reference = $vectors[$referenceAssetId] ?? null;
        if (null === $reference) {
            return [];
        }

        $scores = [];
        foreach ($assetIds as $assetId) {
            if (null !== ($vector = $vectors[$assetId] ?? null)) {
                $scores[$assetId] = (1 + FaceMatcher::cosineSimilarity($reference, $vector)) / 2;
            }
        }

        return $scores;
    }
}
