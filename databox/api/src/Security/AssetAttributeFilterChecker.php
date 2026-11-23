<?php

declare(strict_types=1);

namespace App\Security;

use Alchemy\CoreBundle\Cache\TemporaryCacheFactory;
use App\Elasticsearch\AQL\AQLDocumentEvaluator;
use App\Elasticsearch\AQL\AQLParser;
use App\Elasticsearch\AQL\AQLToESQuery;
use App\Elasticsearch\AQL\UnsupportedEvaluationException;
use App\Elasticsearch\AttributeSearch;
use App\Elasticsearch\ESDocumentStateManager;
use App\Entity\Core\Asset;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Applies the attribute filter rules to a single asset, as the asset search does in its query
 * (AssetSearch::createBaseFilterQueries()): every rule of the workspace of the asset targeting the
 * user must be satisfied. The conditions are evaluated in PHP on the document the indexing would send,
 * so that an asset not indexed yet is evaluated like the others. A condition the search query
 * refuses, or that the PHP evaluation cannot reproduce, denies (fail-closed).
 */
final readonly class AssetAttributeFilterChecker
{
    private CacheInterface $cache;

    public function __construct(
        private AttributeFilterManager $attributeFilterManager,
        private AttributeSearch $attributeSearch,
        private AQLParser $parser,
        private AQLToESQuery $queryBuilder,
        private AQLDocumentEvaluator $evaluator,
        private ESDocumentStateManager $documentStateManager,
        private LoggerInterface $logger,
        TemporaryCacheFactory $cacheFactory,
    ) {
        $this->cache = $cacheFactory->createCache();
    }

    /**
     * @param string[] $groupIds
     */
    public function isReadable(Asset $asset, ?string $userId, array $groupIds): bool
    {
        $workspaceId = $asset->getWorkspaceId();
        $conditions = $this->attributeFilterManager->getUserRules($userId, $groupIds)[$workspaceId] ?? [];
        if (empty($conditions)) {
            return true;
        }

        return $this->cache->get(
            self::getDecisionKey($asset->getId(), $userId, $groupIds),
            fn (): bool => $this->evaluate($asset, $workspaceId, $conditions, $userId, $groupIds),
        );
    }

    /**
     * Declares assets returned by an asset search of this user, whose query applied the rules with
     * the options the PHP evaluation uses, so that voting on them does not evaluate the rules again.
     *
     * @param string[] $groupIds the groups the search ran with
     * @param string[] $assetIds
     */
    public function markReadable(?string $userId, array $groupIds, array $assetIds): void
    {
        foreach ($assetIds as $assetId) {
            // A decision already taken in PHP is kept
            $this->cache->get(self::getDecisionKey($assetId, $userId, $groupIds), static fn (): bool => true);
        }
    }

    private static function getDecisionKey(string $assetId, ?string $userId, array $groupIds): string
    {
        $groupIds = array_values(array_unique($groupIds));
        sort($groupIds);

        return 'd_'.md5(json_encode([$assetId, $userId, $groupIds], JSON_THROW_ON_ERROR));
    }

    /**
     * @param string[] $conditions
     */
    private function evaluate(Asset $asset, string $workspaceId, array $conditions, ?string $userId, array $groupIds): bool
    {
        $options = [
            'userId' => $userId,
            'groupIds' => $groupIds,
            AQLToESQuery::OPT_TRUSTED_CONDITIONS => true,
        ];
        $fieldClusters = $this->attributeSearch->buildAllAttributeDefinitionsGroups();

        $document = null;
        foreach ($conditions as $condition) {
            try {
                $ast = $this->parser->parse($condition);
                if (null === $ast) {
                    throw new \InvalidArgumentException(sprintf('Invalid condition: %s', $condition));
                }

                // The search hides the whole workspace when its query cannot be built
                $this->queryBuilder->createQuery($fieldClusters, $ast['data'], $options);

                if (null === $document) {
                    $document = $this->documentStateManager->createDocument($asset)->getData();
                    if (!is_array($document)) {
                        throw new \RuntimeException('Unexpected asset document');
                    }
                }

                if (!$this->evaluator->evaluate($fieldClusters, $ast['data'], $document, $asset->getId(), $options)) {
                    return false;
                }
            } catch (UnsupportedEvaluationException $e) {
                $this->logger->warning('Attribute filter rule condition not evaluable outside of Elasticsearch', [
                    'workspaceId' => $workspaceId,
                    'condition' => $condition,
                    'assetId' => $asset->getId(),
                    'error' => $e->getMessage(),
                ]);

                return false;
            } catch (\Throwable $e) {
                $this->logger->error('Invalid attribute filter rule condition', [
                    'workspaceId' => $workspaceId,
                    'condition' => $condition,
                    'error' => $e->getMessage(),
                ]);

                return false;
            }
        }

        return true;
    }
}
