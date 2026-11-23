<?php

declare(strict_types=1);

namespace App\Elasticsearch\AQL;

use App\Attribute\AttributeInterface;
use App\Attribute\Type\AttributeTypeInterface;
use App\Attribute\Type\DateTimeAttributeType;
use App\Elasticsearch\BuiltInAttribute\CustomFilterQueryBuiltInAttributeInterface;
use App\Elasticsearch\HiddenAttributeScope;
use Elastica\Query;
use Elastica\Script\Script;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final readonly class AQLToESQuery
{
    /**
     * Marks conditions written by an administrator (attribute filter rules) rather than by the
     * user running the search: the built-in attributes disabled for this user stay usable in them.
     */
    final public const string OPT_TRUSTED_CONDITIONS = 'trustedConditions';

    /**
     * Longest value of a wildcard match ("contains", "ends with"): each one is scanned
     * against every term of the field, in every locale.
     */
    final public const int MAX_WILDCARD_VALUE_LENGTH = 100;

    public function __construct(
        private AQLConditionResolver $resolver,
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
            default => throw new InvalidAQLException(sprintf('Unsupported node type "%s"', $data['type'])),
        };
    }

    private function visitExpression(array $fieldClusters, array $data, array $options): Query\AbstractQuery
    {
        $boolQuery = new Query\BoolQuery();
        $method = match (strtoupper((string) $data['operator'])) {
            LogicOperatorEnum::AND->value => 'addMust',
            LogicOperatorEnum::NOT->value => 'addMustNot',
            default => 'addShould',
        };

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
            || (isset($data['rightOperand']) && !$this->resolver->isResolvableValue($data['rightOperand']))
        ) {
            return $this->visitCriteriaWithScripting($fieldClusters, $data, $options);
        }

        $fields = $this->resolver->getFieldNames($fieldClusters, $leftOperand['field'], $options);
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
        if (!empty($group->getWorkspaceIds()) || !empty($group->getHiddenScopes())) {
            $boolQuery = new Query\BoolQuery();
            $boolQuery->addMust($query);
            if (!empty($group->getWorkspaceIds())) {
                $boolQuery->addMust(new Query\Terms('workspaceId', $group->getWorkspaceIds()));
            }
            foreach ($group->getHiddenScopes() as $scope) {
                $boolQuery->addMustNot(HiddenAttributeScope::createQuery($scope));
            }
            $query = $boolQuery;
        }

        return $query;
    }

    private function createCriteria(array $field, array $data, array $options): Query\AbstractQuery
    {
        $criteria = $this->resolver->resolveCriteria($field, $data, $options);
        if ($criteria->builtInAttribute instanceof CustomFilterQueryBuiltInAttributeInterface) {
            return $criteria->builtInAttribute->createFilterQuery($criteria->value, $criteria->operator, $options);
        }

        $fieldName = $criteria->getFieldName();
        $fieldRaw = $criteria->getRawFieldName();
        $operator = $criteria->operator;
        $value = $criteria->value;
        $type = $criteria->type;
        $caseInsensitive = $criteria->caseInsensitive;

        return match ($operator) {
            ConditionOperatorEnum::BETWEEN, ConditionOperatorEnum::NOT_BETWEEN => $this->wrapInNotQuery(new Query\Range($fieldName, $this->createRangeParams($value, $type)), ConditionOperatorEnum::NOT_BETWEEN === $operator),
            ConditionOperatorEnum::MISSING, ConditionOperatorEnum::EXISTS => $this->wrapInNotQuery($this->yieldShouldQuery($fieldName, $field['locales'], fn (string $fn) => new Query\Exists($fn)), ConditionOperatorEnum::MISSING === $operator),
            ConditionOperatorEnum::IN, ConditionOperatorEnum::NOT_IN => $this->wrapInNotQuery($this->yieldShouldQuery($fieldName, $field['locales'], fn (string $fn) => new Query\Terms($fn, $value)), ConditionOperatorEnum::NOT_IN === $operator),
            ConditionOperatorEnum::HAS_ALL_OF => $this->createHasAllOfQuery($fieldName, $field['locales'], $value),
            ConditionOperatorEnum::EQUALS, ConditionOperatorEnum::NOT_EQUALS => $this->wrapInNotQuery($this->createTermQuery($fieldName, $value), ConditionOperatorEnum::NOT_EQUALS === $operator),
            ConditionOperatorEnum::MATCHES, ConditionOperatorEnum::NOT_MATCHES => $this->wrapInNotQuery($this->createMatchQuery($fieldName, $value), ConditionOperatorEnum::NOT_MATCHES === $operator),
            ConditionOperatorEnum::LT => new Query\Range($fieldName, [
                'lt' => $value,
            ]),
            ConditionOperatorEnum::LTE => new Query\Range($fieldName, [
                'lte' => $value,
            ]),
            ConditionOperatorEnum::GT => new Query\Range($fieldName, [
                'gt' => $value,
            ]),
            ConditionOperatorEnum::GTE => new Query\Range($fieldName, [
                'gte' => $value,
            ]),
            ConditionOperatorEnum::WITHIN_CIRCLE => (new Query\GeoDistance($fieldName, $this->createPoint($value[0], $value[1]), $value[2])),
            ConditionOperatorEnum::WITHIN_RECTANGLE => (new Query\GeoBoundingBox($fieldName, [
                $this->createPoint($value[0], $value[1]),
                $this->createPoint($value[2], $value[3]),
            ])),
            ConditionOperatorEnum::CONTAINS,
            ConditionOperatorEnum::NOT_CONTAINS => $this->wrapInNotQuery(
                $this->yieldShouldQuery($fieldRaw, $field['locales'], fn (string $fn) => $this->createWildcardQuery($fn, sprintf('*%s*', $this->escapeWildcard((string) $value)), $caseInsensitive)),
                ConditionOperatorEnum::NOT_CONTAINS === $operator
            ),
            ConditionOperatorEnum::STARTS_WITH,
            ConditionOperatorEnum::NOT_STARTS_WITH => $this->wrapInNotQuery(
                $this->yieldShouldQuery($fieldRaw, $field['locales'], fn (string $fn) => $this->createPrefixQuery($fn, $value, $caseInsensitive)),
                ConditionOperatorEnum::NOT_STARTS_WITH === $operator
            ),
            ConditionOperatorEnum::ENDS_WITH,
            ConditionOperatorEnum::NOT_ENDS_WITH => $this->wrapInNotQuery(
                $this->yieldShouldQuery($fieldRaw, $field['locales'], fn (string $fn) => $this->createWildcardQuery($fn, '*'.$this->escapeWildcard((string) $value), $caseInsensitive)),
                ConditionOperatorEnum::NOT_ENDS_WITH === $operator
            ),
            default => throw new BadRequestHttpException(sprintf('Operator "%s" not implemented', $operator->value)),
        };
    }

    private function createRangeParams(array $values, AttributeTypeInterface $attributeType): array
    {
        $range = [
            'gte' => $values[0],
            'lte' => $values[1],
        ];

        if ($attributeType instanceof DateTimeAttributeType) {
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

    private function createPrefixQuery(string $fieldName, mixed $value, bool $caseInsensitive): Query\Prefix
    {
        $params = ['value' => $value, 'boost' => 1.0];
        if ($caseInsensitive) {
            $params['case_insensitive'] = true;
        }

        return new Query\Prefix([$fieldName => $params]);
    }

    private function escapeWildcard(string $value): string
    {
        return addcslashes($value, '\\*?');
    }

    private function createWildcardQuery(string $fieldName, string $pattern, bool $caseInsensitive): Query\Wildcard
    {
        $query = new Query\Wildcard($fieldName, $pattern);
        if ($caseInsensitive) {
            $query->setCaseInsensitive(true);
        }

        return $query;
    }

    private function createMatchQuery(string $fieldName, mixed $value): Query\AbstractQuery
    {
        if (str_contains($fieldName, '*')) {
            return new Query\MultiMatch()
                ->setQuery($value)
                ->setFields([$fieldName])
                ->setOperator(Query\MultiMatch::OPERATOR_AND);
        }

        return new Query\MatchQuery()
            ->setFieldQuery($fieldName, $value)
            ->setFieldOperator($fieldName, Query\MatchQuery::OPERATOR_AND);
    }

    private function createHasAllOfQuery(string $fieldName, array $locales, array $values): Query\BoolQuery
    {
        $boolQuery = new Query\BoolQuery();
        foreach ($values as $value) {
            $boolQuery->addMust($this->yieldShouldQuery($fieldName, $locales, fn (string $fn) => new Query\Term([$fn => $value])));
        }

        return $boolQuery;
    }

    private function createTermQuery(string $fieldName, mixed $value): Query\AbstractQuery
    {
        if (str_contains($fieldName, '*')) {
            return new Query\MultiMatch()->setQuery($value)->setFields([$fieldName]);
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

    private function visitCriteriaWithScripting(array $fieldClusters, array $data, array $options): Query\AbstractQuery
    {
        $params = new ScriptParams();
        $queries = $this->expressionToScript($data, $fieldClusters, $options, $params);

        $queries = array_map(
            function (ClusterGroup $q) use ($params): Query\AbstractQuery {
                $script = new Query\Script(new Script($this->createScriptSource($q, $params), $params->all() ?: null));

                return $this->wrapCluster($script, $q);
            },
            $queries
        );

        if (empty($queries)) {
            throw new InvalidAQLException('The fields of the condition cannot be compared');
        }

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

    /**
     * The functions called by the expression are declared first. An asset missing a value read by
     * the expression matches no operator, "!=" included, rather than failing on a null value.
     */
    private function createScriptSource(ClusterGroup $group, ScriptParams $params): string
    {
        $statements = $params->getDeclarations();
        if (!empty($group->getScriptFields())) {
            $statements[] = sprintf('if (%s) { return false; }', implode(' || ', array_map(
                static fn (string $fieldName): string => sprintf('doc["%s"].empty', $fieldName),
                $group->getScriptFields(),
            )));
        }
        $statements[] = sprintf('return %s;', $group->getItem());

        return implode(' ', $statements);
    }

    /**
     * @return ClusterGroup[] the script expression, per group of fields it reads
     */
    private function expressionToScript(mixed $node, array $fieldClusters, array $options, ScriptParams $params): array
    {
        $scripts = [];

        // A value that does not depend on the asset is computed here and passed as a parameter
        if ($this->resolver->isResolvableValue($node)) {
            return [new ClusterGroup($params->add($this->resolver->resolveValue($node)), true)];
        }

        if (is_array($node)) {
            $type = $node['type'] ?? null;
            if (null !== $type) {
                switch ($type) {
                    case 'criteria':
                        $operator = ConditionOperatorEnum::tryFrom($node['operator']);
                        $scriptOperator = match ($operator) {
                            // "=" is an assignment in Painless
                            ConditionOperatorEnum::EQUALS => '==',
                            ConditionOperatorEnum::NOT_EQUALS,
                            ConditionOperatorEnum::GT,
                            ConditionOperatorEnum::GTE,
                            ConditionOperatorEnum::LT,
                            ConditionOperatorEnum::LTE => $operator->value,
                            default => throw new BadRequestHttpException(sprintf('Unsupported operator "%s" in script conditions', $node['operator'])),
                        };
                        $lefts = $this->expressionToScript($node['leftOperand'], $fieldClusters, $options, $params);
                        $rights = $this->expressionToScript($node['rightOperand'] ?? null, $fieldClusters, $options, $params);

                        $scripts = ClusterGroup::mix(
                            $lefts,
                            $rights,
                            fn (string $l, string $r): string => sprintf('%s %s %s', $l, $scriptOperator, $r)
                        );
                        break;
                    case 'value_expression':
                        $operator = ExpressionOperatorEnum::tryFrom($node['operator']);
                        if (null === $operator) {
                            throw new BadRequestHttpException(sprintf('Unsupported operator "%s"', $node['operator']));
                        }
                        if (ExpressionOperatorEnum::DIVIDE === $operator
                            && $this->resolver->isResolvableValue($node['rightOperand'])
                            && 0 == $this->resolver->resolveValue($node['rightOperand'])
                        ) {
                            throw new InvalidAQLException('Division by zero');
                        }
                        $lefts = $this->expressionToScript($node['leftOperand'], $fieldClusters, $options, $params);
                        $rights = $this->expressionToScript($node['rightOperand'], $fieldClusters, $options, $params);

                        $scripts = ClusterGroup::mix(
                            $lefts,
                            $rights,
                            // Painless divides integers as integers, a constant division gives a decimal number
                            fn (string $l, string $r): string => ExpressionOperatorEnum::DIVIDE === $operator
                                ? sprintf('(%s * 1.0 / %s)', $l, $r)
                                : sprintf('(%s %s %s)', $l, $operator->value, $r)
                        );
                        break;
                    case 'function_call':
                        $functionHandle = $this->resolver->getCheckedFunctionHandle($node);
                        if (null !== $declaration = $functionHandle->getScriptDeclaration()) {
                            $params->addDeclaration($functionHandle::getName(), $declaration);
                        }
                        $args = [];
                        foreach ($node['arguments'] as $i => $arg) {
                            $args[] = $this->resolver->isResolvableValue($arg)
                                ? [new ClusterGroup($params->add($this->resolver->resolveArgument($functionHandle, $node['function'], $i, $arg)), true)]
                                : $this->expressionToScript($arg, $fieldClusters, $options, $params);
                        }

                        $mixedArguments = [new ClusterGroup([], true)];
                        foreach ($args as $arg) {
                            $arg = array_map(fn (ClusterGroup $a): ClusterGroup => $a->convert([$a->getItem()]), $arg);
                            $mixedArguments = ClusterGroup::mix($mixedArguments, $arg, fn (array $l, array $r): array => array_merge($l, $r));
                        }

                        foreach ($mixedArguments as $args) {
                            $scripts[] = $args->convert($functionHandle->getScript($args->getItem()));
                        }
                        break;
                    case 'parentheses':
                        $scripts = $this->expressionToScript($node['expression'], $fieldClusters, $options, $params);
                        break;
                    default:
                        throw new InvalidAQLException(sprintf('Unsupported node type "%s" in script conditions', is_scalar($type) ? $type : get_debug_type($type)));
                }
            } elseif (isset($node['field'])) {
                $fields = $this->resolver->getFieldNames($fieldClusters, $node['field'], $options);
                if (empty($fields)) {
                    throw new BadRequestHttpException(sprintf('Field "%s" not found', $node['field']));
                }

                foreach ($fields as $field) {
                    foreach ($this->resolver->resolveScriptFieldNames($field, $options) as $locale => $fieldName) {
                        $scripts[] = $field
                            ->convert(sprintf('doc["%s"].value', $fieldName))
                            ->withFieldLocale(is_string($locale) ? $locale : null)
                            ->withScriptField($fieldName);
                    }
                }
            } else {
                throw new InvalidAQLException('Unsupported value in script conditions');
            }
        } else {
            throw new InvalidAQLException('Unsupported value in script conditions');
        }

        return $scripts;
    }
}
