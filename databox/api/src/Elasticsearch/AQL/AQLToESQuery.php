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

    public function createQuery(array $data): Query\AbstractQuery
    {
        return $this->visitNode($data);
    }

    private function visitNode(array $data): Query\AbstractQuery
    {
        switch ($data['type']) {
            case 'expression':
                return $this->visitExpression($data);
            case 'criteria':
                return $this->visitCriteria($data);
            default:
                throw new \Exception(sprintf('Unsupported node type "%s"', $data['type']));
        }
    }

    private function visitExpression(array $data): Query\AbstractQuery
    {
        $boolQuery = new Query\BoolQuery();
        $method = $data['operator'] === 'and' ? 'addMust' : 'addShould';

        foreach ($data['conditions'] as $condition) {
            $boolQuery->$method($condition);
            $this->visitNode($condition);
        }

        return $boolQuery;
    }

    private function visitCriteria(array $data): Query\AbstractQuery
    {
        $fieldName = $this->getFieldName($data['leftOperand']['field']);
        if (isset($data['rightOperand'])) {
            $value = $data['rightOperand'];
            if ($value['field'] ?? false) {
                return $this->visitCriteriaWithScripting($data);
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
            'MATCHES' => new Query\MatchQuery($fieldName, $value),
            'CONTAINS' => new Query\MatchPhrase($fieldName, sprintf('*%s*', $value)),
            'STARTS_WITH' => new Query\Prefix([$fieldName => $value]),
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

    private function visitCriteriaWithScripting(array $data): Query\AbstractQuery
    {
        switch ($data['operator']) {
            case '=':
            case '<':
            case '<=':
            case '>=':
            case '>':
                return new Query\Script(sprintf(
                    'doc["%s"].value %s %s',
                    $this->getFieldName($data['leftOperand']['field']),
                    $data['operator'],
                    $data['rightOperand']['field']
                ));
            default:
                throw new BadRequestHttpException(sprintf('Invalid operator "%s"', $data['operator']));
        }
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

    private function getFieldName(string $field): string
    {
        if (str_starts_with($field, '@')) {
            $facet = $this->facetRegistry->getFacet($field);
            if (null !== $facet) {
                return $facet->getFieldName();
            } else {
                $key = substr($field, 1);

                return match ($key) {
                    'id' => '_id',
                };
            }
        }

        return sprintf('%s.*.%s', AttributeInterface::ATTRIBUTES_FIELD, $field);
    }
}
