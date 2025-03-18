<?php

namespace App\Elasticsearch\AQL;

use App\Attribute\AttributeInterface;
use App\Elasticsearch\Facet\FacetRegistry;
use Elastica\Query;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final readonly class AQLToESQuery
{
    public function __construct(
        private FacetRegistry $facetRegistry,
    )
    {
    }

    public function createQuery(array $fieldClusters, array $data, array $options): Query\AbstractQuery
    {
        return $this->visitNode($fieldClusters, $data, $options);
    }

    private function visitNode(array $fieldClusters, array $data, array $options): Query\AbstractQuery
    {
        switch ($data['type']) {
            case 'expression':
                return $this->visitExpression($fieldClusters, $data, $options);
            case 'criteria':
                return $this->visitCriteria($fieldClusters, $data, $options);
            default:
                throw new \Exception(sprintf('Unsupported node type "%s"', $data['type']));
        }
    }

    private function visitExpression(array $fieldClusters, array $data, array $options): Query\AbstractQuery
    {
        $boolQuery = new Query\BoolQuery();
        $method = strtoupper($data['operator']) === 'AND' ? 'addMust' : 'addShould';

        foreach ($data['conditions'] as $condition) {
            $boolQuery->$method($this->visitNode($fieldClusters, $condition, $options));
        }

        return $boolQuery;
    }

    private function visitCriteria(array $fieldClusters, array $data, array $options): Query\AbstractQuery
    {
        $language = $options['locale'] ?? '*';
        $queries = [];
        $fields = $this->getFieldNames($fieldClusters, $data['leftOperand']['field']);
        foreach ($fields as $field) {
            $query = $this->createCriteria($fieldClusters, str_replace('{l}', $language, $field['field']), $data);

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

        if (count($queries) === 1) {
            return $queries[0];
        }

        $boolQuery = new Query\BoolQuery();

        foreach ($queries as $query) {
            $boolQuery->addShould($query);
        }

        return $boolQuery;
    }

    private function createCriteria(array $fieldClusters, string $fieldName, array $data): Query\AbstractQuery
    {
        if (isset($data['rightOperand'])) {
            $value = $data['rightOperand'];
            if ($value['field'] ?? false) {
                return $this->visitCriteriaWithScripting($fieldClusters, $fieldName, $data);
            }

            $value = $this->resolveValue($value);
        } else {
            $value = null;
        }

        return match ($data['operator']) {
            'BETWEEN', 'NOT_BETWEEN' => $this->wrapInNotQuery(new Query\Range($fieldName, [
                'gte' => $value[0],
                'lte' => $value[1],
                'format' => 'epoch_second'
            ]), $data['operator'] === 'NOT_BETWEEN'),
            'MISSING', 'EXISTS' => $this->wrapInNotQuery(new Query\Exists($fieldName), $data['operator'] === 'MISSING'),
            'IN', 'NOT_IN' => $this->wrapInNotQuery(new Query\Terms($fieldName, $value), $data['operator'] === 'NOT_IN'),
            '=' => new Query\Term([$fieldName => $value]),
            '!=' => $this->wrapInNotQuery(new Query\Term([$fieldName => $value])),
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
            'MATCHES' => (new Query\MultiMatch())->setQuery($value)->setFields([$fieldName]),
            'CONTAINS' => (new Query\MultiMatch())->setType('phrase')->setQuery(sprintf('*%s*', $value))->setFields([$fieldName]),
            'STARTS_WITH' => (new Query\MultiMatch())->setType('phrase_prefix')->setQuery($value)->setFields([$fieldName]),
            default => throw new BadRequestHttpException(sprintf('Invalid operator "%s"', $data['operator'])),
        };
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

    private function visitCriteriaWithScripting(array $fieldClusters, string $leftFieldName, array $data): Query\AbstractQuery
    {
        $queries = [];
        $rightFieldSlug = $data['rightOperand']['field'];
        $fields = $this->getFieldNames($fieldClusters, $rightFieldSlug);
        foreach ($fields as $rightField) {
            $query = match ($data['operator']) {
                '=', '!=', '<', '<=', '>=', '>' => (new Query\Script(sprintf(
                    '!doc["%1$s"].empty && !doc["%3$s"].empty && doc["%1$s"].value %2$s doc["%3$s"].value',
                    $leftFieldName,
                    $data['operator'],
                    $rightField['field']
                ))),
                default => throw new BadRequestHttpException(sprintf('Unsupported operator "%s"', $data['operator'])),
            };

            if ($rightField['w'] ?? false) {
                $boolQuery = new Query\BoolQuery();
                $boolQuery->addMust($query);
                $boolQuery->addMust(new Query\Term(['workspaceId' => $rightField['w']]));
                $query = $boolQuery;
            }

            if (1 !== ($rightField['b'] ?? 1)) {
                $query->setParam('boost', $rightField['b']);
            }
            $queries[] = $query;
        }

        if (empty($queries)) {
            throw new BadRequestHttpException(sprintf('Field "%s" not found', $rightFieldSlug));
        }

        if (count($queries) === 1) {
            return $queries[0];
        }

        $boolQuery = new Query\BoolQuery();

        foreach ($queries as $query) {
            $boolQuery->addShould($query);
        }

        return $boolQuery;
    }

    private function resolveValue(mixed $data): mixed
    {
        if ($data['literal'] ?? false) {
            return $data['literal'];
        }

        if (is_array($data) && isset($data[0])) {
            return array_map([$this, 'resolveValue'], $data);
        }

        return $data;
    }

    private function getFieldNames(array $fieldClusters, string $fieldSlug): array
    {
        if (str_starts_with($fieldSlug, '@')) {
            $facet = $this->facetRegistry->getFacet($fieldSlug);
            if (null !== $facet) {
                return [['field' => $facet->getFieldName()]];
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
            foreach ($cluster['fields'] as $cField => $fieldConf) {
                foreach ($nameCandidates as $nameCandidate) {
                    if (str_starts_with($cField, $nameCandidate)) {
                        $fields[] = [
                            'field' => $cField,
                            'w' => $cluster['w'],
                        ];
                    }
                }
            }
        }

        return $fields;
    }
}
