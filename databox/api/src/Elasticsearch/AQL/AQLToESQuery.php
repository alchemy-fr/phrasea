<?php

namespace App\Elasticsearch\AQL;

use App\Attribute\AttributeInterface;
use App\Attribute\AttributeTypeRegistry;
use App\Attribute\Type\AttributeTypeInterface;
use App\Attribute\Type\DateTimeAttributeType;
use App\Attribute\Type\KeywordAttributeType;
use App\Elasticsearch\AQL\Function\AQLFunctionInterface;
use App\Elasticsearch\AQL\Function\AQLFunctionRegistry;
use App\Elasticsearch\AQL\Function\Argument;
use App\Elasticsearch\Facet\FacetInterface;
use App\Elasticsearch\Facet\FacetRegistry;
use Elastica\Query;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final readonly class AQLToESQuery
{
    public function __construct(
        private FacetRegistry $facetRegistry,
        private AQLFunctionRegistry $functionRegistry,
        private AttributeTypeRegistry $attributeTypeRegistry,
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
        $leftOperand = $data['leftOperand'];
        if (!isset($leftOperand['field'])
            || (isset($data['rightOperand']) && !$this->isResolvableValue($data['rightOperand']))
        ) {
            return $this->visitCriteriaWithScripting($fieldClusters, $data);
        }

        $fields = $this->getFieldNames($fieldClusters, $leftOperand['field']);
        foreach ($fields as $fieldGroup) {
            $field = $fieldGroup->getItem();
            $query = $this->createCriteria($field, $data, $options);

            $queries[] = $this->wrapCluster($query, $fieldGroup);
        }

        if (empty($queries)) {
            throw new BadRequestHttpException(sprintf('Field "%s" not found', $leftOperand['field']));
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

    private function wrapCluster(Query\AbstractQuery $query, ClusterGroup $group): Query\AbstractQuery
    {
        if (!empty($group->getWorkspaceIds())) {
            $boolQuery = new Query\BoolQuery();
            $boolQuery->addMust($query);
            $boolQuery->addMust(new Query\Terms('workspaceId', $group->getWorkspaceIds()));
            $query = $boolQuery;
        }

        return $query;
    }

    private function createCriteria(array $field, array $data, array $options): Query\AbstractQuery
    {
        $locale = $options['locale'] ?? '*';
        $facet = $field['facet'] ?? null;
        $fieldName = str_replace('{l}', $locale, $field['field']);

        /** @var AttributeTypeInterface $type */
        $type = $field['type'];
        $strictOperators = ['=', '!=', 'IN', 'NOT_IN'];
        if (null !== $type->getElasticSearchRawField() && in_array($data['operator'], $strictOperators, true)) {
            $fieldName .= '.'.$type->getElasticSearchRawField();
        } elseif (null !== $type->getElasticSearchTextSubField() && in_array($data['operator'], $strictOperators + [
            'MATCHES',
            'NOT_MATCHES',
            'CONTAINS',
            'NOT_CONTAINS',
            'STARTS_WITH',
            'NOT_STARTS_WITH',
        ], true)) {
            $fieldName .= '.'.$type->getElasticSearchTextSubField();
        }

        if (isset($data['rightOperand'])) {
            $value = $this->resolveValue($data['rightOperand'], $facet);
        } else {
            $value = null;
        }

        if ($type instanceof DateTimeAttributeType && null !== $value) {
            $this->validateDate($value);
        }

        return match ($data['operator']) {
            'BETWEEN', 'NOT_BETWEEN' => $this->wrapInNotQuery(new Query\Range($fieldName, $this->createRangeParams($value, $type)), 'NOT_BETWEEN' === $data['operator']),
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
            'WITHIN_CIRCLE' => (new Query\GeoDistance($fieldName, $this->createPoint($value[0], $value[1]), $value[2])),
            'WITHIN_RECTANGLE' => (new Query\GeoBoundingBox($fieldName, [
                $this->createPoint($value[0], $value[1]),
                $this->createPoint($value[2], $value[3]),
            ])),
            'CONTAINS', 'NOT_CONTAINS' => $this->wrapInNotQuery((new Query\MultiMatch())->setType('phrase')->setQuery(sprintf('*%s*', $value))->setFields([$fieldName]), 'NOT_CONTAINS' === $data['operator']),
            'STARTS_WITH', 'NOT_STARTS_WITH' => $this->wrapInNotQuery((new Query\MultiMatch())->setType('phrase_prefix')->setQuery($value)->setFields([$fieldName]), 'NOT_STARTS_WITH' === $data['operator']),
            default => throw new BadRequestHttpException(sprintf('Invalid operator "%s"', $data['operator'])),
        };
    }

    private function validateDate($value): void
    {
        if (is_int($value)) {
            return;
        }

        if (is_string($value)) {
            if (is_numeric($value)) {
                return;
            }
            if (10 === strlen($value)) {
                if (false === \DateTimeImmutable::createFromFormat('Y-m-d', $value)) {
                    throw new BadRequestHttpException(sprintf('Invalid date value "%s"', $value));
                }
            } elseif (false === \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.uO', $value)) {
                throw new BadRequestHttpException(sprintf('Invalid date time value "%s"', $value));
            }
        } else {
            throw new BadRequestHttpException(sprintf('Invalid date type "%s"', get_debug_type($value)));
        }
    }

    private function createRangeParams(array $values, AttributeTypeInterface $attributeType): array
    {
        $range = [
            'gte' => $values[0],
            'lte' => $values[1],
        ];

        if ($attributeType instanceof DateTimeAttributeType) {
            $range = array_map(function (mixed $value): string {
                $date = (is_int($value) || is_numeric($value)) ? new \DateTimeImmutable('@'.$value)
                    : new \DateTimeImmutable($value);

                return $date->format('Y-m-d\TH:i:s').'.000Z';
            }, $range);
            $range['format'] = 'strict_date_time';
        }

        return $range;
    }

    private function createPoint(int|float $lat, int|float $lon): array
    {
        return [
            'lat' => $lat,
            'lon' => $lon,
        ];
    }

    private function isResolvableValue(mixed $node): bool
    {
        if (is_array($node)) {
            $type = $node['type'] ?? null;

            return match ($type) {
                'parentheses' => $this->isResolvableValue($node['expression']),
                'function_call' => $this->hasResolvableArguments($node),
                'value_expression' => $this->isResolvableValue($node['leftOperand'])
                    && $this->isResolvableValue($node['rightOperand']),
                default => isset($node['literal'])
                    || (!isset($node['field']) && !array_any($node, fn ($m) => !$this->isResolvableValue($m))),
            };
        }

        return null === $node
            || is_numeric($node)
            || is_bool($node)
        ;
    }

    private function resolveFunctionValue(array $functionNode): mixed
    {
        $functionHandle = $this->getFunctionHandle($functionNode['function']);
        $args = $functionNode['arguments'] ?? [];

        $argCount = count($args);
        $functionArguments = $functionHandle->getArguments();
        $requiredArgs = array_filter($functionArguments, fn (Argument $arg) => $arg->isRequired());
        if ($argCount < count($requiredArgs)) {
            throw new BadRequestHttpException(sprintf('Function "%s" requires at least %d argument(s)', $functionNode['function'], count($requiredArgs)));
        }
        if ($argCount > count($functionArguments)) {
            throw new BadRequestHttpException(sprintf('Function "%s" expects maximum %d argument(s), got %d', $functionNode['function'], count($functionArguments), $argCount));
        }

        $args = array_map(
            fn (mixed $arg) => $this->resolveValue($arg),
            $args
        );

        $result = $functionHandle->resolve($args);
        if (is_string($result)) {
            return ['literal' => $result];
        }

        return $result;
    }

    public function resolveValueExpression(array $exprNode): mixed
    {
        $operator = strtolower($exprNode['operator']);
        $left = $this->resolveValue($exprNode['leftOperand']);
        $right = $this->resolveValue($exprNode['rightOperand']);

        switch ($operator) {
            case '+':
                return $left + $right;
            case '-':
                return $left - $right;
            case '*':
                return $left * $right;
            case '/':
                return $left / $right;
            default:
                throw new BadRequestHttpException(sprintf('Unsupported operator "%s"', $operator));
        }
    }

    private function getFunctionHandle(string $functionName): AQLFunctionInterface
    {
        $functionHandle = $this->functionRegistry->getFunction(strtolower($functionName));
        if (null === $functionHandle) {
            throw new BadRequestHttpException(sprintf('Function "%s" not found', $functionName));
        }

        return $functionHandle;
    }

    private function hasResolvableArguments(array $functionNode): bool
    {
        foreach ($functionNode['arguments'] as $arg) {
            if (!$this->isResolvableValue($arg)) {
                return false;
            }
        }

        return true;
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
            function (ClusterGroup $q): Query\AbstractQuery {
                $script = new Query\Script($q->getItem());

                return $this->wrapCluster($script, $q);
            },
            $queries
        );

        if (1 === count($queries)) {
            return $queries[0];
        }

        $boolQuery = new Query\BoolQuery();
        $boolQuery->setMinimumShouldMatch(1);
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

                        $scripts = ClusterGroup::mix(
                            $lefts,
                            $rights,
                            fn (string $l, string $r) => sprintf('%s %s %s', $l, $node['operator'], $r)
                        );
                        break;
                    case 'value_expression':
                        if (
                            in_array($node['operator'], ['+', '-', '*', '/'], true)
                            && $this->isResolvableValue($node['leftOperand'])
                            && $this->isResolvableValue($node['rightOperand'])
                        ) {
                            $scripts = [new ClusterGroup($this->resolveValueExpression($node), true)];
                        } else {
                            $lefts = $this->expressionToScript($node['leftOperand'], $fieldClusters);
                            $rights = $this->expressionToScript($node['rightOperand'], $fieldClusters);

                            $scripts = ClusterGroup::mix(
                                $lefts,
                                $rights,
                                fn (string $l, string $r): string => sprintf('(%s %s %s)', $l, $node['operator'], $r)
                            );
                        }
                        break;
                    case 'function_call':
                        $functionHandle = $this->getFunctionHandle($node['function']);
                        $args = array_map(fn (mixed $arg) => $this->expressionToScript($arg, $fieldClusters), $node['arguments']);

                        $mixedArguments = [new ClusterGroup([], true)];
                        foreach ($args as $arg) {
                            $arg = array_map(fn (ClusterGroup $a): ClusterGroup => $a->convert([$a->getItem()]), $arg);
                            $mixedArguments = ClusterGroup::mix($mixedArguments, $arg, function (array $l, array $r): array {
                                return array_merge($l, $r);
                            });
                        }

                        foreach ($mixedArguments as $args) {
                            $scripts[] = $args->convert($functionHandle->getScript($args->getItem()));
                        }
                        break;
                    case 'parentheses':
                        $scripts = $this->expressionToScript($node['expression'], $fieldClusters);
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
                    $scripts[] = $field->convert(sprintf('(!doc["%1$s"].empty ? doc["%1$s"].value : null)', $field->getItem()['field']));
                }
            } else {
                throw new \RuntimeException(sprintf('Unsupported node "%s"', print_r($node, true)));
            }
        } else {
            $scripts[] = new ClusterGroup((string) $node, true);
        }

        return $scripts;
    }

    private function resolveValue(mixed $data, ?FacetInterface $facet = null): mixed
    {
        if (is_array($data)) {
            $type = $data['type'] ?? null;

            if ('function_call' === $type) {
                $data = $this->resolveFunctionValue($data);
            } elseif ('parentheses' === $type) {
                $data = $this->resolveValue($data['expression']);
            } elseif ('value_expression' === $type) {
                $data = $this->resolveValueExpression($data);
            } elseif (isset($data[0])) {
                return array_map(function (mixed $data) use ($facet) {
                    return $this->resolveValue($data, $facet);
                }, $data);
            }
        }

        if ($data instanceof \DateTimeInterface) {
            return $data->getTimestamp();
        }

        $v = $data['literal'] ?? $data;

        if (null !== $facet) {
            $v = $facet->normalizeValueForSearch($v);
        }

        return $v;
    }

    /**
     * @return ClusterGroup[]
     */
    private function getFieldNames(array $fieldClusters, string $fieldSlug): array
    {
        if (str_starts_with($fieldSlug, '@')) {
            $facet = $this->facetRegistry->getFacet($fieldSlug);
            if (null !== $facet) {
                return [
                    new ClusterGroup([
                        'field' => $facet->getFieldName(),
                        'facet' => $facet,
                        'type' => $this->attributeTypeRegistry->getStrictType($facet->getType()),
                        'locales' => [],
                    ], true),
                ];
            } else {
                $key = substr($fieldSlug, 1);

                return [
                    new ClusterGroup([
                        'field' => match ($key) {
                            'id' => '_id',
                            'size' => 'fileSize',
                            'type' => 'fileType',
                            'mimetype' => 'fileMimeType',
                            'filename' => 'fileName',
                        },
                        'type' => $this->attributeTypeRegistry->getStrictType(KeywordAttributeType::NAME),
                        'locales' => [],
                    ], true),
                ];
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
                        $fields[] = new ClusterGroup(
                            [
                                'field' => $cField,
                                'type' => $fieldConf['type'],
                                'locales' => $locales,
                            ],
                            false,
                            $cluster['w'] ?? [],
                            $cluster['locales'] ?? null
                        );
                    }
                }
            }
        }

        return $fields;
    }
}
