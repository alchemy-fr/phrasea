<?php

declare(strict_types=1);

namespace App\Security;

use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\Voter\AdminVoter;
use Alchemy\CoreBundle\Cache\TemporaryCacheFactory;
use App\Elasticsearch\AQL\AQLDocumentEvaluator;
use App\Elasticsearch\AQL\AQLParser;
use App\Elasticsearch\AQL\AQLToESQuery;
use App\Elasticsearch\AQL\UnsupportedEvaluationException;
use App\Elasticsearch\AssetIndexPreloader;
use App\Elasticsearch\AssetPermissionComputer;
use App\Elasticsearch\AttributeSearch;
use App\Elasticsearch\ESDocumentStateManager;
use App\Entity\Core\Asset;
use App\Repository\Core\AssetRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Applies the attribute filter rules to a single asset, as the asset search does in its query
 * (AssetSearch::createBaseFilterQueries()): every rule of the workspace of the asset targeting the
 * user must be satisfied. The conditions are evaluated in PHP on the document the indexing would send,
 * so that an asset not indexed yet is evaluated like the others. A condition the search query
 * refuses, or that the PHP evaluation cannot reproduce, denies (fail-closed).
 */
final readonly class AssetAttributeFilterChecker
{
    private ArrayAdapter $cache;

    public function __construct(
        private AttributeFilterManager $attributeFilterManager,
        private AttributeSearch $attributeSearch,
        private AQLParser $parser,
        private AQLToESQuery $queryBuilder,
        private AQLDocumentEvaluator $evaluator,
        private ESDocumentStateManager $documentStateManager,
        private AssetRepository $assetRepository,
        private AssetIndexPreloader $indexPreloader,
        private AssetPermissionComputer $permissionComputer,
        private TokenStorageInterface $tokenStorage,
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
     * Takes at once the decisions on a list of assets about to be voted on, loading what their
     * documents are built from with a few queries for the whole list instead of a few per asset.
     * The decisions are the ones isReadable() would take.
     *
     * @param iterable<Asset> $assets
     * @param string[]        $groupIds
     */
    public function preload(iterable $assets, ?string $userId, array $groupIds): void
    {
        $rules = $this->attributeFilterManager->getUserRules($userId, $groupIds);
        if ([] === array_filter($rules)) {
            return;
        }

        $undecided = [];
        foreach ($assets as $asset) {
            if (!$this->cache->hasItem(self::getDecisionKey($asset->getId(), $userId, $groupIds))) {
                $undecided[$asset->getId()] = $asset;
            }
        }
        if ([] === $undecided) {
            return;
        }

        // Fetch-joins the relations the document reads (collections, source file...): the assets
        // and relations not loaded yet get loaded, those already loaded are kept as they are
        $this->assetRepository->createElasticaToModelQueryBuilder('a')
            ->andWhere('a.id IN (:ids)')
            ->setParameter('ids', array_keys($undecided))
            ->getQuery()
            ->getResult();

        $pending = array_values(array_filter(
            $undecided,
            static fn (Asset $asset): bool => !empty($rules[$asset->getWorkspaceId()] ?? []),
        ));
        if ([] === $pending) {
            return;
        }

        $this->indexPreloader->preload($pending, attributeEmbeddings: true);
        try {
            $this->permissionComputer->runWithPreloadedAssets($pending, function () use ($pending, $userId, $groupIds): void {
                foreach ($pending as $asset) {
                    $this->isReadable($asset, $userId, $groupIds);
                }
            });
        } finally {
            $this->indexPreloader->reset();
        }
    }

    /**
     * preload() for the user of the current token, as AssetVoter sees it.
     *
     * @param iterable<Asset> $assets
     */
    public function preloadForCurrentUser(iterable $assets): void
    {
        $token = $this->tokenStorage->getToken();
        // AdminVoter grants the admins any asset: their votes need no decision
        if (null !== $token && in_array(AdminVoter::ROLE, $token->getRoleNames(), true)) {
            return;
        }

        $user = $token?->getUser();
        if ($user instanceof JwtUser) {
            $this->preload($assets, $user->getId(), $user->getGroups());
        } else {
            $this->preload($assets, null, []);
        }
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

    /**
     * Forgets the decisions taken, which a change of the rules may invalidate
     * (see AttributeFilterRuleCacheListener).
     */
    public function clearCache(): void
    {
        $this->cache->clear();
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
                $ast = $this->getCheckedCondition($condition, $fieldClusters, $options);

                if (null === $document) {
                    $document = $this->documentStateManager->createDocument($asset)->getData();
                    if (!is_array($document)) {
                        throw new \RuntimeException('Unexpected asset document');
                    }
                }

                if (!$this->evaluator->evaluate($fieldClusters, $ast, $document, $asset->getId(), $options)) {
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

    /**
     * The parsed condition, once its search query is known to be built: the search hides the whole
     * workspace when it cannot be. Both only depend on the condition and the user, not on the asset.
     *
     * @throws \Throwable the condition is refused
     */
    private function getCheckedCondition(string $condition, array $fieldClusters, array $options): array
    {
        $checked = $this->cache->get('c_'.md5(json_encode([$condition, $options], JSON_THROW_ON_ERROR)), function () use ($condition, $fieldClusters, $options): array {
            try {
                $ast = $this->parser->parse($condition);
                if (null === $ast) {
                    throw new \InvalidArgumentException(sprintf('Invalid condition: %s', $condition));
                }

                $this->queryBuilder->createQuery($fieldClusters, $ast['data'], $options);

                return ['ast' => $ast['data']];
            } catch (\Throwable $e) {
                return ['error' => $e];
            }
        });

        if (isset($checked['error'])) {
            throw $checked['error'];
        }

        return $checked['ast'];
    }
}
