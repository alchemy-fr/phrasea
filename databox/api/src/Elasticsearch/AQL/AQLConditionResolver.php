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
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Resolves the parts of an AQL condition that do not depend on what runs it: the fields a slug
 * designates, the values, the functions, and the effective operator and field of each criteria.
 * Shared by {@see AQLToESQuery} (Elasticsearch query) and {@see AQLDocumentEvaluator} (PHP evaluation
 * on one document), so that both apply the same rules.
 */
final readonly class AQLConditionResolver
{
    public function __construct(
        private BuiltInAttributeRegistry $builtInAttributeRegistry,
        private AQLFunctionRegistry $functionRegistry,
        private AttributeTypeRegistry $attributeTypeRegistry,
        private DateNormalizer $dateNormalizer,
    ) {
    }

    /**
     * @return ClusterGroup[]
     */
    public function getFieldNames(array $fieldClusters, string $fieldSlug, array $options): array
    {
        if (str_starts_with($fieldSlug, '@')) {
            $builtInAttribute = $this->builtInAttributeRegistry->getBuiltInAttribute($fieldSlug);
            // A built-in attribute disabled for the user does not exist for them
            if (null !== $builtInAttribute
                && ($builtInAttribute->isEnabled() || true === ($options[AQLToESQuery::OPT_TRUSTED_CONDITIONS] ?? false))
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

    /**
     * Resolves a criteria comparing a field to a value that does not depend on the asset, against one
     * of the fields returned by {@see getFieldNames()}. The built-in attributes building their own
     * filter ({@see CustomFilterQueryBuiltInAttributeInterface}) get the operator and value as written.
     */
    public function resolveCriteria(array $field, array $data, array $options): ResolvedCriteria
    {
        $locale = $options['locale'] ?? '*';
        /** @var BuiltInAttributeInterface|null $builtInAttribute */
        $builtInAttribute = $field['builtInAttribute'] ?? null;

        $fieldName = str_replace('{l}', $locale, $field['field']);
        /** @var AttributeTypeInterface $type */
        $type = $field['type'];

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

        $subField = null;
        if (null !== $type->getElasticSearchRawField() && in_array($operator, $strictOperators, true)) {
            $subField = $type->getElasticSearchRawField();
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
            $subField = $type->getElasticSearchTextSubField();
        }

        if (isset($data['rightOperand'])) {
            $value = $this->resolveValue($data['rightOperand'], $builtInAttribute, $options);
        } else {
            $value = null;
        }

        if (!$builtInAttribute instanceof CustomFilterQueryBuiltInAttributeInterface) {
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
            ], true) && is_scalar($value) && mb_strlen((string) $value) > AQLToESQuery::MAX_WILDCARD_VALUE_LENGTH) {
                throw new InvalidAQLException(sprintf('The value of operator "%s" is limited to %d characters', $operator->value, AQLToESQuery::MAX_WILDCARD_VALUE_LENGTH));
            }
        }

        return new ResolvedCriteria(
            field: $fieldName,
            subField: $subField,
            rawSubField: $type->getElasticSearchRawField(),
            operator: $operator,
            value: $value,
            caseInsensitive: $caseInsensitive,
            type: $type,
            locales: $field['locales'],
            builtInAttribute: $builtInAttribute,
        );
    }

    private function normalizeBooleanValue(BooleanAttributeType $type, mixed $value): bool
    {
        $normalized = $type->normalizeValue($value);
        if (!is_bool($normalized)) {
            throw new BadRequestHttpException(sprintf('Invalid boolean value "%s"', is_scalar($value) ? $value : get_debug_type($value)));
        }

        return $normalized;
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

    /**
     * Whether the node is a value that does not depend on the asset.
     */
    public function isResolvableValue(mixed $node): bool
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

    private function hasResolvableArguments(array $functionNode): bool
    {
        foreach ($functionNode['arguments'] as $arg) {
            if (!$this->isResolvableValue($arg)) {
                return false;
            }
        }

        return true;
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

    /**
     * The function of a call node, once its argument count checked.
     */
    public function getCheckedFunctionHandle(array $functionNode): AQLFunctionInterface
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
    public function resolveArgument(AQLFunctionInterface $functionHandle, string $functionName, int $index, mixed $argNode): mixed
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

    /**
     * The fields a script reads for a field of the condition: a translatable field is read in the
     * locale of the search or, without one, in each locale of its workspaces and untranslated.
     * A field with a raw sub-field is read from it (text fields have no doc values, the value of an
     * entity is its ID), except a date, whose raw value is not comparable to a timestamp.
     *
     * @return array<int|string, string> the field names, indexed by locale for a translatable field
     */
    public function resolveScriptFieldNames(ClusterGroup $field, array $options): array
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

    public function resolveValue(mixed $data, ?BuiltInAttributeInterface $builtInAttribute = null, array $options = []): mixed
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
}
