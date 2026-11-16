<?php

declare(strict_types=1);

namespace App\Elasticsearch\AQL;

use App\Attribute\AttributeInterface;
use App\Attribute\AttributeTypeRegistry;
use App\Attribute\Type\AttributeTypeInterface;
use App\Attribute\Type\BooleanAttributeType;
use App\Attribute\Type\DateAttributeType;
use App\Attribute\Type\DateTimeAttributeType;
use App\Attribute\Type\GeoPointAttributeType;
use App\Attribute\Type\KeywordAttributeType;
use App\Attribute\Type\NumberAttributeType;
use App\Attribute\Type\TextAttributeType;
use App\Elasticsearch\AQL\Function\AQLFunctionInterface;
use App\Elasticsearch\AQL\Function\AQLFunctionRegistry;
use App\Elasticsearch\AQL\Function\Argument;
use App\Elasticsearch\AQL\Function\TypeEnum;
use App\Elasticsearch\BuiltInAttribute\BuiltInAttributeInterface;
use App\Elasticsearch\BuiltInAttribute\BuiltInAttributeRegistry;
use App\Elasticsearch\BuiltInAttribute\CustomFilterQueryBuiltInAttributeInterface;
use App\Elasticsearch\HiddenAttributeScope;
use Elastica\Query;
use Elastica\Script\Script;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
        private BuiltInAttributeRegistry $builtInAttributeRegistry,
        private AQLFunctionRegistry $functionRegistry,
        private AttributeTypeRegistry $attributeTypeRegistry,
        private DateNormalizer $dateNormalizer,
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
            || (isset($data['rightOperand']) && !$this->isResolvableValue($data['rightOperand']))
        ) {
            return $this->visitCriteriaWithScripting($fieldClusters, $data, $options);
        }

        $fields = $this->getFieldNames($fieldClusters, $leftOperand['field'], $options);
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

    private function normalizeBooleanValue(BooleanAttributeType $type, mixed $value): bool
    {
        $normalized = $type->normalizeValue($value);
        if (!is_bool($normalized)) {
            throw new BadRequestHttpException(sprintf('Invalid boolean value "%s"', is_scalar($value) ? $value : get_debug_type($value)));
        }

        return $normalized;
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
        $locale = $options['locale'] ?? '*';
        /** @var BuiltInAttributeInterface $builtInAttribute */
        $builtInAttribute = $field['builtInAttribute'] ?? null;

        $fieldName = str_replace('{l}', $locale, $field['field']);
        /** @var AttributeTypeInterface $type */
        $type = $field['type'];
        $fieldRaw = $fieldName.($type->getElasticSearchRawField() ? '.'.$type->getElasticSearchRawField() : '');

        $strictOperators = [ConditionOperatorEnum::EQUALS, ConditionOperatorEnum::NOT_EQUALS, ConditionOperatorEnum::IN, ConditionOperatorEnum::NOT_IN, ConditionOperatorEnum::HAS_ALL_OF];

        $operator = ConditionOperatorEnum::tryFrom($data['operator']);
        if (null === $operator) {
            throw new BadRequestHttpException(sprintf('Unsupported operator "%s"', $data['operator']));
        }

        $this->validateOperator($operator, $type->getName());

        $caseSensitive = $data['caseSensitive'] ?? false;
        if ($caseSensitive && !in_array($operator, [
            ConditionOperatorEnum::CONTAINS,
            ConditionOperatorEnum::NOT_CONTAINS,
            ConditionOperatorEnum::STARTS_WITH,
            ConditionOperatorEnum::NOT_STARTS_WITH,
            ConditionOperatorEnum::ENDS_WITH,
            ConditionOperatorEnum::NOT_ENDS_WITH,
        ], true)) {
            throw new BadRequestHttpException(sprintf('CASE SENSITIVE is not supported with operator "%s"', $operator->value));
        }
        // Dates are switched to STARTS_WITH below, case is irrelevant for them.
        $caseInsensitive = !$caseSensitive && in_array($type->getName(), [TextAttributeType::NAME, KeywordAttributeType::NAME], true);

        if (null !== $type->getElasticSearchRawField() && in_array($operator, $strictOperators, true)) {
            $fieldName .= '.'.$type->getElasticSearchRawField();
        } elseif (null !== $type->getElasticSearchTextSubField() && in_array($operator, [
            ...$strictOperators,
            ConditionOperatorEnum::MATCHES,
            ConditionOperatorEnum::NOT_MATCHES,
            ConditionOperatorEnum::CONTAINS,
            ConditionOperatorEnum::NOT_CONTAINS,
            ConditionOperatorEnum::STARTS_WITH,
            ConditionOperatorEnum::NOT_STARTS_WITH,
            ConditionOperatorEnum::ENDS_WITH,
            ConditionOperatorEnum::NOT_ENDS_WITH,
        ], true)) {
            $fieldName .= '.'.$type->getElasticSearchTextSubField();
        }

        if (isset($data['rightOperand'])) {
            $value = $this->resolveValue($data['rightOperand'], $builtInAttribute, $options);
        } else {
            $value = null;
        }

        if ($builtInAttribute instanceof CustomFilterQueryBuiltInAttributeInterface) {
            return $builtInAttribute->createFilterQuery($value, $operator, $options);
        }

        if ($type instanceof DateTimeAttributeType && null !== $value) {
            $operatorSwitches = [
                ConditionOperatorEnum::EQUALS->value => ConditionOperatorEnum::STARTS_WITH,
                ConditionOperatorEnum::NOT_EQUALS->value => ConditionOperatorEnum::NOT_STARTS_WITH,
            ];

            $switchOperator = $operatorSwitches[$operator->value] ?? null;
            $value = $this->dateNormalizer->normalizeDate(
                $value,
                allowPartialDate: null !== $switchOperator,
                withTimeZone: null === $switchOperator,
            );

            if (null !== $switchOperator) {
                $operator = $switchOperator;
            }
        }

        if ($type instanceof BooleanAttributeType) {
            if (null === $value) {
                $operatorSwitches = [
                    ConditionOperatorEnum::EQUALS->value => ConditionOperatorEnum::MISSING,
                    ConditionOperatorEnum::NOT_EQUALS->value => ConditionOperatorEnum::EXISTS,
                ];

                $operator = $operatorSwitches[$operator->value] ?? $operator;
            } else {
                // Facet buckets of boolean fields are keyed 1/0, which ES refuses in a term query.
                $value = is_array($value)
                    ? array_map(fn (mixed $v): bool => $this->normalizeBooleanValue($type, $v), $value)
                    : $this->normalizeBooleanValue($type, $value);
            }
        }

        if (in_array($operator, [
            ConditionOperatorEnum::CONTAINS,
            ConditionOperatorEnum::NOT_CONTAINS,
            ConditionOperatorEnum::ENDS_WITH,
            ConditionOperatorEnum::NOT_ENDS_WITH,
        ], true) && is_scalar($value) && mb_strlen((string) $value) > self::MAX_WILDCARD_VALUE_LENGTH) {
            throw new InvalidAQLException(sprintf('The value of operator "%s" is limited to %d characters', $operator->value, self::MAX_WILDCARD_VALUE_LENGTH));
        }

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

    private function validateOperator(ConditionOperatorEnum $operator, string $type): void
    {
        $gt = [
            NumberAttributeType::NAME,
            DateAttributeType::NAME,
            DateTimeAttributeType::getName(),
        ];

        $text = [
            TextAttributeType::NAME,
            KeywordAttributeType::NAME,
        ];

        $geo = [
            GeoPointAttributeType::NAME,
        ];

        $operatorSupportedTypes = [
            ConditionOperatorEnum::EQUALS->value => null,
            ConditionOperatorEnum::NOT_EQUALS->value => null,
            ConditionOperatorEnum::GT->value => $gt,
            ConditionOperatorEnum::GTE->value => $gt,
            ConditionOperatorEnum::LT->value => $gt,
            ConditionOperatorEnum::LTE->value => $gt,
            ConditionOperatorEnum::IN->value => null,
            ConditionOperatorEnum::NOT_IN->value => null,
            ConditionOperatorEnum::HAS_ALL_OF->value => null,
            ConditionOperatorEnum::EXISTS->value => null,
            ConditionOperatorEnum::MISSING->value => null,
            ConditionOperatorEnum::BETWEEN->value => $gt,
            ConditionOperatorEnum::NOT_BETWEEN->value => $gt,
            ConditionOperatorEnum::MATCHES->value => $text,
            ConditionOperatorEnum::NOT_MATCHES->value => $text,
            ConditionOperatorEnum::CONTAINS->value => $text,
            ConditionOperatorEnum::NOT_CONTAINS->value => $text,
            ConditionOperatorEnum::STARTS_WITH->value => $text,
            ConditionOperatorEnum::NOT_STARTS_WITH->value => $text,
            ConditionOperatorEnum::ENDS_WITH->value => $text,
            ConditionOperatorEnum::NOT_ENDS_WITH->value => $text,
            ConditionOperatorEnum::WITHIN_CIRCLE->value => $geo,
            ConditionOperatorEnum::WITHIN_RECTANGLE->value => $geo,
        ];

        $supportedTypes = $operatorSupportedTypes[$operator->value] ?? null;
        if (null !== $supportedTypes && !in_array($type, $supportedTypes, true)) {
            throw new BadRequestHttpException(sprintf('Operator "%s" not supported for field type "%s"', $operator->value, $type));
        }
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
        $functionHandle = $this->getCheckedFunctionHandle($functionNode);

        $args = [];
        foreach ($functionNode['arguments'] ?? [] as $i => $arg) {
            $args[] = $this->resolveArgument($functionHandle, $functionNode['function'], $i, $arg);
        }

        $result = $functionHandle->resolve($args);
        if (is_string($result)) {
            return ['literal' => $result];
        }

        return $result;
    }

    public function resolveValueExpression(array $exprNode): mixed
    {
        $operator = ExpressionOperatorEnum::tryFrom($exprNode['operator']);
        if (null === $operator) {
            throw new BadRequestHttpException(sprintf('Unsupported operator "%s"', $exprNode['operator']));
        }
        $left = $this->resolveValue($exprNode['leftOperand']);
        $right = $this->resolveValue($exprNode['rightOperand']);

        foreach ([$left, $right] as $operand) {
            if (!is_int($operand) && !is_float($operand) && !(is_string($operand) && is_numeric($operand))) {
                throw new InvalidAQLException(sprintf('Operator "%s" expects numbers, got %s', $operator->value, self::describeValue($operand)));
            }
        }
        if (ExpressionOperatorEnum::DIVIDE === $operator && 0 == $right) {
            throw new InvalidAQLException('Division by zero');
        }

        return match ($operator) {
            ExpressionOperatorEnum::PLUS => $left + $right,
            ExpressionOperatorEnum::MINUS => $left - $right,
            ExpressionOperatorEnum::MULTIPLY => $left * $right,
            ExpressionOperatorEnum::DIVIDE => $left / $right,
        };
    }

    private function getCheckedFunctionHandle(array $functionNode): AQLFunctionInterface
    {
        $functionHandle = $this->getFunctionHandle($functionNode['function']);

        $argCount = count($functionNode['arguments'] ?? []);
        $functionArguments = $functionHandle->getArguments();
        $requiredArgs = array_filter($functionArguments, fn (Argument $arg) => $arg->isRequired());
        if ($argCount < count($requiredArgs)) {
            throw new BadRequestHttpException(sprintf('Function "%s" requires at least %d argument(s)', $functionNode['function'], count($requiredArgs)));
        }
        if ($argCount > count($functionArguments)) {
            throw new BadRequestHttpException(sprintf('Function "%s" expects maximum %d argument(s), got %d', $functionNode['function'], count($functionArguments), $argCount));
        }

        return $functionHandle;
    }

    /**
     * Resolves an argument that does not depend on the asset, checking its type.
     */
    private function resolveArgument(AQLFunctionInterface $functionHandle, string $functionName, int $index, mixed $argNode): mixed
    {
        $value = $this->resolveValue($argNode);

        /** @var Argument $argument */
        $argument = $functionHandle->getArguments()[$index];
        $valid = match ($argument->getType()) {
            TypeEnum::INTEGER => is_int($value),
            TypeEnum::NUMBER => is_int($value) || is_float($value),
            default => true,
        };
        if (!$valid) {
            throw new InvalidAQLException(sprintf('Argument "%s" of function "%s" must be %s, got %s', $argument->getName(), $functionName, TypeEnum::INTEGER === $argument->getType() ? 'an integer' : 'a number', self::describeValue($value)));
        }

        return $value;
    }

    private static function describeValue(mixed $value): string
    {
        return is_string($value) ? sprintf('"%s"', $value) : get_debug_type($value);
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
        if ($this->isResolvableValue($node)) {
            return [new ClusterGroup($params->add($this->resolveValue($node)), true)];
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
                            && $this->isResolvableValue($node['rightOperand'])
                            && 0 == $this->resolveValue($node['rightOperand'])
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
                        $functionHandle = $this->getCheckedFunctionHandle($node);
                        if (null !== $declaration = $functionHandle->getScriptDeclaration()) {
                            $params->addDeclaration($functionHandle::getName(), $declaration);
                        }
                        $args = [];
                        foreach ($node['arguments'] as $i => $arg) {
                            $args[] = $this->isResolvableValue($arg)
                                ? [new ClusterGroup($params->add($this->resolveArgument($functionHandle, $node['function'], $i, $arg)), true)]
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
                $fields = $this->getFieldNames($fieldClusters, $node['field'], $options);
                if (empty($fields)) {
                    throw new BadRequestHttpException(sprintf('Field "%s" not found', $node['field']));
                }

                foreach ($fields as $field) {
                    foreach ($this->resolveScriptFieldNames($field, $options) as $locale => $fieldName) {
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

    /**
     * The fields a script reads for a field of the condition: a translatable field is read in the
     * locale of the search or, without one, in each locale of its workspaces and untranslated.
     * A field with a raw sub-field is read from it (text fields have no doc values, the value of an
     * entity is its ID), except a date, whose raw value is not comparable to a timestamp.
     *
     * @return array<int|string, string> the field names, indexed by locale for a translatable field
     */
    private function resolveScriptFieldNames(ClusterGroup $field, array $options): array
    {
        $fieldName = $field->getItem()['field'];
        /** @var AttributeTypeInterface $type */
        $type = $field->getItem()['type'];
        if (!$type instanceof DateTimeAttributeType && null !== $rawField = $type->getElasticSearchRawField()) {
            $fieldName .= '.'.$rawField;
        }
        if (!str_contains($fieldName, '{l}')) {
            return [$fieldName];
        }

        if (null !== $locale = $options['locale'] ?? null) {
            return [$locale => str_replace('{l}', $locale, $fieldName)];
        }

        $names = [];
        foreach ([...($field->getItem()['locales'] ?? []), AttributeInterface::NO_LOCALE] as $locale) {
            $names[$locale] = str_replace('{l}', $locale, $fieldName);
        }

        return $names;
    }

    private function resolveValue(mixed $data, ?BuiltInAttributeInterface $builtInAttribute = null, array $options = []): mixed
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
                return array_map(fn (mixed $data) => $this->resolveValue($data, $builtInAttribute, $options), $data);
            }
        }

        if ($data instanceof \DateTimeInterface) {
            return $data->getTimestamp();
        }

        $v = $data['literal'] ?? $data;

        if (null !== $builtInAttribute) {
            try {
                $v = $builtInAttribute->normalizeValueForSearch($v, $options);
            } catch (NotFoundHttpException $e) {
                throw new BadRequestHttpException(sprintf('Invalid value for built-in field "%s": %s', $builtInAttribute::getName(), $e->getMessage()), $e);
            }
        }

        return $v;
    }

    /**
     * @return ClusterGroup[]
     */
    private function getFieldNames(array $fieldClusters, string $fieldSlug, array $options): array
    {
        if (str_starts_with($fieldSlug, '@')) {
            $builtInAttribute = $this->builtInAttributeRegistry->getBuiltInAttribute($fieldSlug);
            // A built-in attribute disabled for the user does not exist for them
            if (null !== $builtInAttribute
                && ($builtInAttribute->isEnabled() || true === ($options[self::OPT_TRUSTED_CONDITIONS] ?? false))
            ) {
                return [
                    new ClusterGroup([
                        'field' => $builtInAttribute::getName(),
                        'builtInAttribute' => $builtInAttribute,
                        'type' => $this->attributeTypeRegistry->getStrictType($builtInAttribute->getType()),
                        'locales' => [],
                    ], true),
                ];
            }

            throw new BadRequestHttpException(sprintf('Built-in field "%s" not found', $fieldSlug));
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
                    if (str_starts_with((string) $cField, $nameCandidate.'_')) {
                        $fields[] = new ClusterGroup(
                            [
                                'field' => $cField,
                                'type' => $fieldConf['type'],
                                'locales' => $locales,
                            ],
                            false,
                            $cluster['w'] ?? [],
                            $cluster['locales'] ?? null,
                            $cluster['x'] ?? [],
                        );
                    }
                }
            }
        }

        return $fields;
    }
}
