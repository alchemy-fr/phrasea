<?php

namespace App\Elasticsearch\AQL;

use App\Attribute\AttributeInterface;
use Elastica\Query;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final readonly class AQLToESQuery
{
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
        $value = $data['rightOperand'];
        if ($value['field'] ?? false) {
            return $this->visitCriteriaWithScripting($data);
        }

        $fieldName = $this->getFieldName($data['leftOperand']['field']);
        $value = $this->resolveValue($value);
        switch ($data['operator']) {
            case 'BETWEEN':
                return new Query\Range($fieldName, [
                    'gte' => $value[0],
                    'lte' => $value[1],
                ]);;
            case '=':
                return new Query\Term([$fieldName => $value]);
            case '<':
                return new Query\Range($fieldName, [
                    'lt' => $value,
                ]);
            case '<=':
                return new Query\Range($fieldName, [
                    'lte' => $value,
                ]);
            case '>=':
                return new Query\Range($fieldName, [
                    'gte' => $value,
                ]);
            case '>':
                return new Query\Range($fieldName, [
                    'gt' => $value,
                ]);
            default:
                throw new BadRequestHttpException(sprintf('Invalid operator "%s"', $data['operator']));
        }
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
            $key = substr($field, 1);

            return match ($key) {
                'id' => '_id',
                'createdAt' => 'createdAt',
                'updatedAt' => 'updatedAt',
                'workspace' => 'workspaceId',
                'collection' => 'collectionPaths',
                'score' => '_score',
            };
        }

        return sprintf('%s.*.%s', AttributeInterface::ATTRIBUTES_FIELD, $field);
    }
}
