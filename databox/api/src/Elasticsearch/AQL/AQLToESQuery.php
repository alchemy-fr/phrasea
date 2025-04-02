<?php

namespace App\Elasticsearch\AQL;

use App\Attribute\AttributeInterface;
use App\Elasticsearch\Facet\FacetInterface;
use App\Elasticsearch\Facet\FacetRegistry;
use Elastica\Query;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final readonly class AQLToESQuery
{
    public function __construct(
        private FacetRegistry $facetRegistry,
    ) {
    }

    public function createQuery(array $fieldClusters, array $data, array $options): Query\AbstractQuery
    {
        return $this->visitNode($fieldClusters, $data, $options);
    }

    private function visitNode(array $fieldClusters, array $data, array $options): Query\AbstractQuery
    {
        return match ($data['type']) {
            'expression' => $this->visitExpression($fieldClusters, $data, $options),
            'criteria' => $this->visitCriteria($fieldClusters, $data, $options),
            default => throw new \Exception(sprintf('Unsupported node type "%s"', $data['type'])),
        };
    }

    private function visitExpression(array $fieldClusters, array $data, array $options): Query\AbstractQuery
    {
        $boolQuery = new Query\BoolQuery();
        $method = 'AND' === strtoupper($data['operator']) ? 'addMust' : 'addShould';

        foreach ($data['conditions'] as $condition) {
            $boolQuery->$method($this->visitNode($fieldClusters, $condition, $options));
        }

        return $boolQuery;
    }

    private function visitCriteria(array $fieldClusters, array $data, array $options): Query\AbstractQuery
    {
        $queries = [];
        $fields = $this->getFieldNames($fieldClusters, $data['leftOperand']['field']);
        foreach ($fields as $field) {
            $query = $this->createCriteria($fieldClusters, $field, $data, $field['facet'] ?? null, $options);

            if ($field['w'] ?? false) {
                $boolQuery = new Query\BoolQuery();
                $boolQuery->addMust($query);
                $boolQuery->addMust(new Query\Term(['workspaceId' => $field['w']]));
                $query = $boolQuery;
            }

            if (1 !== ($field['b'] ?? 1)) {
                $query->setParam('boost', $field['b']);
            }
            $queries[] = $query;
        }

        if (empty($queries)) {
            throw new BadRequestHttpException(sprintf('Field "%s" not found', $data['leftOperand']['field']));
        }

        if (1 === count($queries)) {
            return $queries[0];
        }

        $boolQuery = new Query\BoolQuery();

        foreach ($queries as $query) {
            $boolQuery->addShould($query);
        }

        return $boolQuery;
    }

    private function createCriteria(array $fieldClusters, array $field, array $data, ?FacetInterface $facet, array $options): Query\AbstractQuery
    {
        $locale = $options['locale'] ?? '*';
        $fieldName = str_replace('{l}', $locale, $field['field']);
        if (($field['raw'] ?? false) && in_array($data['operator'], ['=', '!=', 'IN', 'NOT_IN'], true)) {
            $fieldName .= '.'.$field['raw'];
        }

        if (isset($data['rightOperand'])) {
            $value = $data['rightOperand'];
            if (!$this->isValue($value)) {
                return $this->visitCriteriaWithScripting($fieldClusters, $data);
            }

            $value = $this->resolveValue($value, $facet);
        } else {
            $value = null;
        }

        return match ($data['operator']) {
            'BETWEEN', 'NOT_BETWEEN' => $this->wrapInNotQuery(new Query\Range($fieldName, [
                'gte' => $value[0],
                'lte' => $value[1],
                'format' => is_numeric($value[0]) ? 'epoch_second' : 'date_optional_time',
            ]), 'NOT_BETWEEN' === $data['operator']),
            'MISSING', 'EXISTS' => $this->wrapInNotQuery($this->yieldShouldQuery($fieldName, $field['locales'], function (string $fn) {
                return new Query\Exists($fn);
            }), 'MISSING' === $data['operator']),
            'IN', 'NOT_IN' => $this->wrapInNotQuery($this->yieldShouldQuery($fieldName, $field['locales'], function (string $fn) use ($value) {
                return new Query\Terms($fn, $value);
            }), 'NOT_IN' === $data['operator']),
            '=', 'MATCHES', '!=', 'NOT_MATCHES' => $this->wrapInNotQuery($this->createTermQuery($fieldName, $value), in_array($data['operator'], ['!=', 'NOT_MATCHES'], true)),
            '<' => new Query\Range($fieldName, [
                'lt' => $value,
            ]),
            '<=' => new Query\Range($fieldName, [
                'lte' => $value,
            ]),
            '>=' => new Query\Range($fieldName, [
                'gte' => $value,
            ]),
            '>' => new Query\Range($fieldName, [
                'gt' => $value,
            ]),
            'CONTAINS', 'NOT_CONTAINS' => $this->wrapInNotQuery((new Query\MultiMatch())->setType('phrase')->setQuery(sprintf('*%s*', $value))->setFields([$fieldName]), 'NOT_CONTAINS' === $data['operator']),
            'STARTS_WITH', 'NOT_STARTS_WITH' => $this->wrapInNotQuery((new Query\MultiMatch())->setType('phrase_prefix')->setQuery($value)->setFields([$fieldName]), 'NOT_STARTS_WITH' === $data['operator']),
            default => throw new BadRequestHttpException(sprintf('Invalid operator "%s"', $data['operator'])),
        };
    }

    private function isValue(mixed $node): bool
    {
        if (is_array($node)) {
            return isset($node['literal'])
                || (!isset($node['field']) && !array_any($node, fn ($m) => !$this->isValue($m)))
            ;
        }

        return null === $node
            || is_numeric($node)
            || is_bool($node)
        ;
    }

    private function yieldShouldQuery(string $fieldName, array $locales, \Closure $createQuery): Query\AbstractQuery
    {
        if (!str_contains($fieldName, '*')) {
            return $createQuery($fieldName);
        }

        $locales[] = AttributeInterface::NO_LOCALE;
        $boolQuery = new Query\BoolQuery();
        foreach ($locales as $locale) {
            $boolQuery->addShould($createQuery(str_replace('*', $locale, $fieldName)));
        }

        return $boolQuery;
    }

    private function createTermQuery(string $fieldName, mixed $value): Query\AbstractQuery
    {
        if (str_contains($fieldName, '*')) {
            return (new Query\MultiMatch())->setQuery($value)->setFields([$fieldName]);
        }

        return new Query\Term([$fieldName => $value]);
    }

    private function wrapInNotQuery(Query\AbstractQuery $query, bool $condition = true): Query\AbstractQuery
    {
        if (!$condition) {
            return $query;
        }

        $not = new Query\BoolQuery();
        $not->addMustNot($query);

        return $not;
    }

    private function visitCriteriaWithScripting(array $fieldClusters, array $data): Query\AbstractQuery
    {
        $queries = $this->expressionToScript($data, $fieldClusters);

        $queries = array_map(
            fn (string $q) => new Query\Script($q),
            $queries
        );

        if (1 === count($queries)) {
            return $queries[0];
        }

        $boolQuery = new Query\BoolQuery();

        foreach ($queries as $query) {
            $boolQuery->addShould($query);
        }

        return $boolQuery;
    }

    private function expressionToScript(mixed $node, array $fieldClusters): array
    {
        $scripts = [];

        if (is_array($node)) {
            $type = $node['type'] ?? null;
            if (null !== $type) {
                switch ($type) {
                    case 'criteria':
                        if (!in_array($node['operator'], [
                            '=', '!=', '<', '<=', '>=', '>',
                        ], true)) {
                            throw new BadRequestHttpException(sprintf('Unsupported operator "%s" in script conditions', $node['operator']));
                        }
                        $lefts = $this->expressionToScript($node['leftOperand'], $fieldClusters);
                        $rights = $this->expressionToScript($node['rightOperand'], $fieldClusters);

                        foreach ($lefts as $left) {
                            foreach ($rights as $right) {
                                $scripts[] = sprintf('%s %s %s',
                                    $left,
                                    $node['operator'],
                                    $right,
                                );
                            }
                        }
                        break;
                    case 'value_expression':
                        $lefts = $this->expressionToScript($node['leftOperand'], $fieldClusters);
                        $rights = $this->expressionToScript($node['rightOperand'], $fieldClusters);
                        foreach ($lefts as $left) {
                            foreach ($rights as $right) {
                                // TODO handle clustered fields (workspace)
                                //            if ($rightField['w'] ?? false) {
                                //                $boolQuery = new Query\BoolQuery();
                                //                $boolQuery->addMust($query);
                                //                $boolQuery->addMust(new Query\Term(['workspaceId' => $rightField['w']]));
                                //                $query = $boolQuery;
                                //            }
                                //
                                //            if (1 !== ($rightField['b'] ?? 1)) {
                                //                $query->setParam('boost', $rightField['b']);
                                //            }

                                $scripts[] = sprintf('(%s %s %s)',
                                    $left,
                                    $node['operator'],
                                    $right,
                                );
                            }
                        }
                        break;
                    default:
                        throw new \RuntimeException(sprintf('Unsupported node type "%s"', $type));
                }
            } elseif (isset($node['field'])) {
                $fields = $this->getFieldNames($fieldClusters, $node['field']);
                if (empty($fields)) {
                    throw new BadRequestHttpException(sprintf('Field "%s" not found', $node['field']));
                }

                foreach ($fields as $field) {
                    $scripts[] = sprintf('(!doc["%1$s"].empty ? doc["%1$s"].value : null)', $field['field']);
                }
            } else {
                throw new \RuntimeException(sprintf('Unsupported node "%s"', print_r($node, true)));
            }
        } else {
            $scripts[] = (string) $node;
        }

        return $scripts;
    }

    private function resolveValue(mixed $data, ?FacetInterface $facet): mixed
    {
        if (is_array($data) && isset($data[0])) {
            return array_map(function (mixed $data) use ($facet) {
                return $this->resolveValue($data, $facet);
            }, $data);
        }

        $v = $data['literal'] ?? $data;

        if (null !== $facet) {
            $v = $facet->normalizeValueForSearch($v);
        }

        return $v;
    }

    private function getFieldNames(array $fieldClusters, string $fieldSlug): array
    {
        if (str_starts_with($fieldSlug, '@')) {
            $facet = $this->facetRegistry->getFacet($fieldSlug);
            if (null !== $facet) {
                return [
                    [
                        'field' => $facet->getFieldName(),
                        'facet' => $facet,
                    ],
                ];
            } else {
                $key = substr($fieldSlug, 1);

                return [['field' => match ($key) {
                    'id' => '_id',
                }]];
            }
        }

        $nameCandidates = [
            sprintf('%s.%s.%s', AttributeInterface::ATTRIBUTES_FIELD, '_', $fieldSlug),
            sprintf('%s.%s.%s', AttributeInterface::ATTRIBUTES_FIELD, '{l}', $fieldSlug),
        ];
        $fields = [];
        foreach ($fieldClusters as $cluster) {
            $locales = $cluster['locales'] ?? [];
            foreach ($cluster['fields'] as $cField => $fieldConf) {
                foreach ($nameCandidates as $nameCandidate) {
                    if (str_starts_with($cField, $nameCandidate.'_')) {
                        $fields[] = [
                            'field' => $cField,
                            'w' => $cluster['w'],
                            'raw' => $fieldConf['raw'],
                            'locales' => $locales,
                        ];
                    }
                }
            }
        }

        return $fields;
    }
}
