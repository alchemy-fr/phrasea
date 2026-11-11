<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use Alchemy\CoreBundle\Util\LocaleUtil;
use ApiPlatform\Metadata\Operation;
use App\Api\Traits\UserLocaleTrait;
use App\Elasticsearch\Filter\SearchQuery;
use App\Entity\Core\Collection;
use App\Entity\Core\Workspace;
use App\Repository\Core\CollectionRepository;
use App\Security\Voter\AbstractVoter;
use App\Security\Voter\CollectionVoter;
use Elastica\Query;
use FOS\ElasticaBundle\Finder\PaginatedFinderInterface;
use Pagerfanta\Pagerfanta;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class CollectionSearch extends AbstractSearch
{
    use UserLocaleTrait;

    /**
     * Sort key of the displayed name (see Collection::getSortNames()): picks the translation chosen by
     * LocaleUtil::getBestLocale() for the preferred locales of the workspace of the document, the name otherwise.
     *
     * params.prefs: workspace ID => list of {raw: locale, n: normalized locale, lang: language of n when it has a region}.
     */
    private const string SORT_SCRIPT = <<<'PAINLESS'
        String fallback = doc['sortName'].size() == 0 ? '' : doc['sortName'].value;
        if (!doc.containsKey('sortNames') || doc['sortNames'].size() == 0 || doc['workspaceId'].size() == 0) { return fallback; }
        List prefs = params.prefs.get(doc['workspaceId'].value);
        if (prefs == null) { return fallback; }
        String sep = params.sep;
        List avail = new ArrayList();
        Map keys = new HashMap();
        for (String v : doc['sortNames']) {
          int s1 = v.indexOf(sep);
          int s2 = v.indexOf(sep, s1 + 1);
          String loc = v.substring(s1 + 1, s2);
          if (!keys.containsKey(loc)) { avail.add(loc); keys.put(loc, v.substring(s2 + 1)); }
        }
        int n = prefs.size();
        for (int i = 0; i < n; ++i) {
          Map p = prefs.get(i);
          String l = p.n;
          if (keys.containsKey(l)) { return keys.get(l); }
          if (p.lang != null) {
            if (keys.containsKey(p.lang)) { return keys.get(p.lang); }
            l = p.lang;
          } else {
            for (int j = i + 1; j < n; ++j) {
              String next = prefs.get(j).raw;
              if (next.startsWith(l + '_') && keys.containsKey(next)) { return keys.get(next); }
            }
          }
          String prefix = l + '_';
          for (String a : avail) { if (a.startsWith(prefix)) { return keys.get(a); } }
        }
        return fallback;
        PAINLESS;

    public function __construct(
        #[Autowire(service: 'fos_elastica.finder.collection')]
        private readonly PaginatedFinderInterface $finder,
        private readonly QueryStringParser $queryStringParser,
        private readonly CollectionRepository $collectionRepository,
    ) {
    }

    public function search(
        ?string $userId,
        array $groupIds,
        array $options = [],
        ?Operation $operation = null,
    ): Pagerfanta {
        $maxLimit = 50;

        $filterQuery = new Query\BoolQuery();
        $parentCollections = $this->applyFilters($filterQuery, $userId, $groupIds, $options);

        $searchQuery = new SearchQuery($filterQuery);
        $this->applyParameters($searchQuery, Collection::class, $operation, $options);

        $query = new Query();
        $query->setQuery($filterQuery);
        $query->setTrackTotalHits();
        $query->setSort($searchQuery->hasSort() ? $searchQuery->getSort() : $this->getDefaultSort($parentCollections, $options));

        if (!empty($options['query'])) {
            $parsed = $this->queryStringParser->parseQuery((string) $options['query']);
            $partialMatch = new Query\MultiMatch();
            $partialMatch->setQuery(implode(' ', [$parsed['should'], ...$parsed['must']]));
            $partialMatch->setFields(['name.suggest', 'translatedNames.suggest']);
            $partialHighlightQuery = $partialMatch->toArray();

            $query->setHighlight([
                'pre_tags' => ['[hl]'],
                'post_tags' => ['[/hl]'],
                'fields' => [
                    // Only the typed part of the words, from the edge n-grams of the name
                    // (see CollectionOutputMapper::getNameHighlight())
                    'name.suggest' => [
                        'fragment_size' => 255,
                        'number_of_fragments' => 1,
                        'highlight_query' => $partialHighlightQuery,
                    ],
                    'translatedNames.suggest' => [
                        'fragment_size' => 255,
                        'number_of_fragments' => 0,
                        'highlight_query' => $partialHighlightQuery,
                    ],
                    'name' => [
                        'fragment_size' => 255,
                        'number_of_fragments' => 1,
                    ],
                    // one fragment per matching translation, to pick the one of the displayed name
                    'translatedNames' => [
                        'fragment_size' => 255,
                        'number_of_fragments' => 0,
                    ],
                ],
            ]);
        }

        $data = $this->finder->findPaginated($query);
        self::applyPagination($data, $options, $maxLimit);
        $this->executeSearch($data->getCurrentPageResults(...));

        return $data;
    }

    /**
     * Same order as CollectionSorter: displayed name (natural order), then creation date, then ID.
     *
     * @param Collection[] $parentCollections
     */
    private function getDefaultSort(array $parentCollections, array $options): array
    {
        if ([] !== $parentCollections) {
            $workspaces = array_map(static fn (Collection $c): Workspace => $c->getWorkspace(), $parentCollections);
        } elseif (!empty($workspaceIds = self::toIdList($options['workspaces'] ?? null))) {
            $workspaces = $this->workspaceRepository->findBy(['id' => $workspaceIds]);
        } else {
            $workspaces = $this->workspaceRepository->findAll();
        }

        $prefs = [];
        foreach ($workspaces as $workspace) {
            $prefs[$workspace->getId()] ??= array_map(static function (string $raw): array {
                $n = LocaleUtil::normalizeLocale($raw);

                return [
                    'raw' => $raw,
                    'n' => $n,
                    'lang' => str_contains($n, '_') ? explode('_', $n)[0] : null,
                ];
            }, array_values($this->getPreferredLocales($workspace)));
        }

        return [
            ['_script' => [
                'type' => 'string',
                'order' => 'asc',
                'script' => [
                    'lang' => 'painless',
                    'source' => self::SORT_SCRIPT,
                    'params' => [
                        'sep' => Collection::SORT_SEP,
                        'prefs' => (object) $prefs,
                    ],
                ],
            ]],
            ['createdAt' => ['order' => 'asc']],
            ['id' => ['order' => 'asc', 'unmapped_type' => 'keyword']],
        ];
    }

    /**
     * @return Collection[] the parent collections the search is restricted to
     */
    private function applyFilters(
        Query\BoolQuery $boolQuery,
        ?string $userId,
        array $groupIds,
        array $options = [],
    ): array {
        $aclBoolQuery = $this->createACLBoolQuery($userId, $groupIds);

        $queryString = trim((string) ($options['query'] ?? ''));
        $parsed = $this->queryStringParser->parseQuery($queryString);
        // "deep" is a boolean parameter on the API; callers from PHP may pass any truthy/falsy value
        $deep = filter_var($options['deep'] ?? !empty($queryString), FILTER_VALIDATE_BOOLEAN);

        if (!empty($parsed['should'])) {
            $searchBool = new Query\BoolQuery();
            $searchBool->addShould(self::createNameMatch($parsed['should']));
            $boolQuery->addMust($searchBool);
        }
        foreach ($parsed['must'] as $must) {
            $boolQuery->addMust(self::createNameMatch($must));
        }

        $includeDeleted = false;
        foreach ($parsed['filters'] as $filter) {
            if (isset($filter['in'])) {
                switch ($filter['in']) {
                    case 'all':
                        $includeDeleted = true;
                        break;
                    case 'trash':
                        $boolQuery->addFilter(new Query\Term(['deleted' => true]));
                        $includeDeleted = true;
                        break;
                }
            }
        }

        if (!$includeDeleted) {
            $boolQuery->addFilter(new Query\Term(['deleted' => false]));
        }

        if (null !== $aclBoolQuery) {
            $boolQuery->addFilter($aclBoolQuery);
        }

        if (!empty($options['parent'])) {
            $options['parents'] = [$options['parent']];
        }

        $parentCollections = [];
        if (!empty($parentIds = self::toIdList($options['parents'] ?? null))) {
            $parentCollections = DoctrineUtil::getFromIds($this->collectionRepository, $parentIds);
            $parentsBoolQuery = new Query\BoolQuery();
            array_map(function (Collection $parentCollection) use ($parentsBoolQuery, $deep): void {
                $q = new Query\BoolQuery();
                $q->addFilter(new Query\Term(['absolutePath' => $parentCollection->getAbsolutePath()]));

                if (!$deep) {
                    $q->addFilter(new Query\Term(['pathDepth' => $parentCollection->getPathDepth() + 1]));
                } else {
                    $q->addFilter(new Query\Range('pathDepth', ['gte' => $parentCollection->getPathDepth() + 1]));
                }
                $parentsBoolQuery->addMust($q);
            }, $parentCollections);

            $boolQuery->addFilter($parentsBoolQuery);
        } elseif (!$deep) {
            $boolQuery->addFilter(new Query\Term(['pathDepth' => 0]));
        }

        if (!empty($workspaceIds = self::toIdList($options['workspaces'] ?? null))) {
            $boolQuery->addFilter(
                new Query\Terms('workspaceId', $workspaceIds)
            );
        }

        return $parentCollections;
    }

    /**
     * Matches the name or any of its translations, the last word being a prefix (search-as-you-type).
     */
    private static function createNameMatch(string $text): Query\MultiMatch
    {
        $match = new Query\MultiMatch();
        $match->setQuery($text);
        $match->setType(Query\MultiMatch::TYPE_BOOL_PREFIX);
        $match->setOperator(Query\MultiMatch::OPERATOR_AND);
        $match->setFields(['name', 'translatedNames']);

        return $match;
    }

    protected function getAdminScope(): ?string
    {
        return CollectionVoter::SCOPE_PREFIX.AbstractVoter::LIST;
    }
}
