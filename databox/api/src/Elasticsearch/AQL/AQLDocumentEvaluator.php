<?php

declare(strict_types=1);

namespace App\Elasticsearch\AQL;

use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use App\Attribute\AttributeInterface;
use App\Attribute\AttributeLocaleInterface;
use App\Attribute\Type\AssetStatusAttributeType;
use App\Attribute\Type\AttributeTypeInterface;
use App\Attribute\Type\DateTimeAttributeType;
use App\Attribute\Type\EntityAttributeType;
use App\Elasticsearch\AbstractSearch;
use App\Elasticsearch\AQL\Function\ConcatFunction;
use App\Elasticsearch\AQL\Function\SubstringFunction;
use App\Elasticsearch\BuiltInAttribute\AssetStatusBuiltInAttribute;
use App\Elasticsearch\BuiltInAttribute\BuiltInAttributeInterface;
use App\Elasticsearch\BuiltInAttribute\CustomFilterQueryBuiltInAttributeInterface;
use App\Elasticsearch\BuiltInAttribute\DeletedBuiltInAttribute;
use App\Elasticsearch\HiddenAttributeScope;
use App\Entity\Core\AssetStatusEnum;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Evaluates an AQL condition on the Elasticsearch document of an asset built in memory (the one the
 * indexing would send), with the semantics of the query {@see AQLToESQuery} builds for the asset
 * index: the same fields, clusters, hidden scopes, values and operators, the operator applied in PHP.
 *
 * The condition is compiled before reading the document, so that a condition the query builder
 * refuses throws whatever the document. Whatever cannot be reproduced exactly throws
 * {@see UnsupportedEvaluationException} (see {@see DocumentTextAnalyzer} for the text analysis):
 * callers deny on any exception.
 */
final readonly class AQLDocumentEvaluator
{
    public const string ID_FIELD = '_id';

    /**
     * GeoUtils::EARTH_MEAN_RADIUS of Elasticsearch.
     */
    private const float EARTH_RADIUS = 6371008.7714;

    private const array DISTANCE_UNITS = [
        'mi' => 1609.344, 'miles' => 1609.344,
        'yd' => 0.9144, 'yards' => 0.9144,
        'ft' => 0.3048, 'feet' => 0.3048,
        'in' => 0.0254, 'inch' => 0.0254,
        'km' => 1000.0, 'kilometers' => 1000.0,
        'm' => 1.0, 'meters' => 1.0,
        'cm' => 0.01, 'centimeters' => 0.01,
        'mm' => 0.001, 'millimeters' => 0.001,
        'nmi' => 1852.0, 'nauticalmiles' => 1852.0,
    ];

    private const array INTEGER_RANGES = [
        'long' => [PHP_INT_MIN, PHP_INT_MAX],
        'integer' => [-2147483648, 2147483647],
        'short' => [-32768, 32767],
        'byte' => [-128, 127],
    ];

    /**
     * 2^53: from there on, a float no longer holds every integer, while Elasticsearch compares the
     * integer fields exactly.
     */
    private const float MAX_EXACT_FLOAT_INTEGER = 9007199254740992.0;

    private const array KEYWORD = ['type' => 'keyword'];
    private const array PATH = ['type' => 'text', 'analyzer' => 'collection_path', 'search_analyzer' => 'keyword'];
    private const array DATE = ['type' => 'date', 'fields' => ['raw' => self::KEYWORD]];

    /**
     * The mapping of the fields of the asset index the built-in attributes query (fos_elastica.yaml).
     */
    private const array BUILT_IN_MAPPINGS = [
        self::ID_FIELD => ['type' => self::ID_FIELD],
        'collectionPaths' => [...self::PATH, 'fields' => ['raw' => self::KEYWORD]],
        'storyCollectionPaths' => self::PATH,
        'referenceCollectionPath' => [...self::PATH, 'fields' => ['raw' => self::KEYWORD]],
        'referenceCollectionId' => self::KEYWORD,
        'privacy' => ['type' => 'byte'],
        'stories' => self::KEYWORD,
        'renditions' => self::KEYWORD,
        'isStory' => ['type' => 'boolean'],
        'workspaceId' => self::KEYWORD,
        'ownerId' => self::KEYWORD,
        'hasSourceFile' => ['type' => 'boolean'],
        'fileSize' => ['type' => 'long'],
        'fileName' => ['type' => 'text', 'fields' => ['raw' => self::KEYWORD]],
        'fileType' => self::KEYWORD,
        'fileExtension' => self::KEYWORD,
        'fileFamily' => self::KEYWORD,
        'checksum' => self::KEYWORD,
        'docUniqueId' => self::KEYWORD,
        'createdAt' => self::DATE,
        'editedAt' => self::DATE,
        'attributesEditedAt' => self::DATE,
    ];

    public function __construct(
        private AQLConditionResolver $resolver,
    ) {
    }

    /**
     * @param array $ast      the "data" of the parsed condition
     * @param array $document the source of the Elasticsearch document of the asset
     * @param array $options  as for {@see AQLToESQuery::createQuery()}: "userId", "groupIds", "locale"…
     */
    public function evaluate(array $fieldClusters, array $ast, array $document, string $documentId, array $options): bool
    {
        $document[self::ID_FIELD] = $documentId;

        return $this->compileNode($fieldClusters, $ast, $options)($document);
    }

    /**
     * @return \Closure(array): bool
     */
    private function compileNode(array $fieldClusters, array $data, array $options): \Closure
    {
        return match ($data['type']) {
            'expression' => $this->compileExpression($fieldClusters, $data, $options),
            'criteria' => $this->compileCriteria($fieldClusters, $data, $options),
            default => throw new InvalidAQLException(sprintf('Unsupported node type "%s"', $data['type'])),
        };
    }

    private function compileExpression(array $fieldClusters, array $data, array $options): \Closure
    {
        $conditions = array_map(fn (array $c): \Closure => $this->compileNode($fieldClusters, $c, $options), $data['conditions']);

        return match (strtoupper((string) $data['operator'])) {
            LogicOperatorEnum::AND->value => static fn (array $doc): bool => array_all($conditions, static fn (\Closure $c): bool => $c($doc)),
            LogicOperatorEnum::NOT->value => static fn (array $doc): bool => !array_any($conditions, static fn (\Closure $c): bool => $c($doc)),
            default => static fn (array $doc): bool => array_any($conditions, static fn (\Closure $c): bool => $c($doc)),
        };
    }

    private function compileCriteria(array $fieldClusters, array $data, array $options): \Closure
    {
        $leftOperand = $data['leftOperand'];
        if (!isset($leftOperand['field'])
            || (isset($data['rightOperand']) && !$this->resolver->isResolvableValue($data['rightOperand']))
        ) {
            return $this->compileScriptedCriteria($fieldClusters, $data, $options);
        }

        $matchers = [];
        foreach ($this->resolver->getFieldNames($fieldClusters, $leftOperand['field'], $options) as $group) {
            $matchers[] = $this->wrapCluster($this->compileFieldCriteria($group->getItem(), $data, $options), $group);
        }

        if (empty($matchers)) {
            throw new BadRequestHttpException(sprintf('Field "%s" not found', $leftOperand['field']));
        }

        return self::any($matchers);
    }

    /**
     * Mirror of AQLToESQuery::wrapCluster(): the workspaces of the cluster, minus the assets on which
     * asset policies hide its fields.
     */
    private function wrapCluster(\Closure $matcher, ClusterGroup $group): \Closure
    {
        $workspaceIds = $group->getWorkspaceIds();
        $hiddenScopes = $group->getHiddenScopes();
        if (empty($workspaceIds) && empty($hiddenScopes)) {
            return $matcher;
        }

        return function (array $doc) use ($matcher, $workspaceIds, $hiddenScopes): bool {
            if (!empty($workspaceIds) && empty(array_intersect($this->readKeywords($doc, 'workspaceId'), $workspaceIds))) {
                return false;
            }
            foreach ($hiddenScopes as $scope) {
                if (HiddenAttributeScope::coversDocument($scope, $doc)) {
                    return false;
                }
            }

            return $matcher($doc);
        };
    }

    private function compileFieldCriteria(array $field, array $data, array $options): \Closure
    {
        $criteria = $this->resolver->resolveCriteria($field, $data, $options);
        $builtInAttribute = $criteria->builtInAttribute;

        if ($builtInAttribute instanceof CustomFilterQueryBuiltInAttributeInterface) {
            return $this->compileCustomBuiltIn($builtInAttribute, $criteria->value, $criteria->operator, $options);
        }

        $operator = $criteria->operator;
        $value = $criteria->value;
        $negate = static fn (\Closure $m, bool $negate): \Closure => $negate ? static fn (array $doc): bool => !$m($doc) : $m;

        if ($criteria->type instanceof EntityAttributeType && null !== $value && in_array($operator, [
            ConditionOperatorEnum::EQUALS,
            ConditionOperatorEnum::NOT_EQUALS,
            ConditionOperatorEnum::IN,
            ConditionOperatorEnum::NOT_IN,
            ConditionOperatorEnum::HAS_ALL_OF,
        ], true)) {
            return $negate($this->compileEntityBranch($criteria, $value), in_array($operator, [ConditionOperatorEnum::NOT_EQUALS, ConditionOperatorEnum::NOT_IN], true));
        }

        switch ($operator) {
            case ConditionOperatorEnum::BETWEEN:
            case ConditionOperatorEnum::NOT_BETWEEN:
                if (!is_array($value) || !array_key_exists(0, $value) || !array_key_exists(1, $value)) {
                    throw new UnsupportedEvaluationException('BETWEEN expects two values');
                }
                $strict = $criteria->type instanceof DateTimeAttributeType;
                $ref = $this->createSingleRef($criteria, $criteria->subField);

                return $negate($this->compileRange($ref, [
                    'gte' => $this->compileBound($ref, $value[0], false, $strict),
                    'lte' => $this->compileBound($ref, $value[1], true, $strict),
                ]), ConditionOperatorEnum::NOT_BETWEEN === $operator);
            case ConditionOperatorEnum::LT:
            case ConditionOperatorEnum::LTE:
            case ConditionOperatorEnum::GT:
            case ConditionOperatorEnum::GTE:
                $ref = $this->createSingleRef($criteria, $criteria->subField);
                $bound = match ($operator) {
                    ConditionOperatorEnum::LT => ['lt', false],
                    ConditionOperatorEnum::LTE => ['lte', true],
                    ConditionOperatorEnum::GT => ['gt', true],
                    default => ['gte', false],
                };

                return $this->compileRange($ref, [$bound[0] => $this->compileBound($ref, $value, $bound[1])]);
            case ConditionOperatorEnum::MISSING:
            case ConditionOperatorEnum::EXISTS:
                $refs = $this->createLocaleRefs($criteria, $criteria->subField);

                return $negate(fn (array $doc): bool => array_any($refs, fn (array $ref): bool => $this->exists($doc, $ref)), ConditionOperatorEnum::MISSING === $operator);
            case ConditionOperatorEnum::IN:
            case ConditionOperatorEnum::NOT_IN:
                $refs = $this->createLocaleRefs($criteria, $criteria->subField);
                $values = self::assertList($value);
                $terms = array_map(fn (array $ref): \Closure => $this->compileTerms($ref, $values), $refs);

                return $negate(self::any($terms), ConditionOperatorEnum::NOT_IN === $operator);
            case ConditionOperatorEnum::HAS_ALL_OF:
                $refs = $this->createLocaleRefs($criteria, $criteria->subField);
                $all = [];
                foreach (self::assertList($value) as $v) {
                    $all[] = self::any(array_map(fn (array $ref): \Closure => $this->compileTerms($ref, [$v]), $refs));
                }

                return static fn (array $doc): bool => array_all($all, static fn (\Closure $m): bool => $m($doc));
            case ConditionOperatorEnum::EQUALS:
            case ConditionOperatorEnum::NOT_EQUALS:
                return $negate($this->compileTermQuery($criteria, $value), ConditionOperatorEnum::NOT_EQUALS === $operator);
            case ConditionOperatorEnum::MATCHES:
            case ConditionOperatorEnum::NOT_MATCHES:
                return $negate($this->compileMatchQuery($criteria, $value), ConditionOperatorEnum::NOT_MATCHES === $operator);
            case ConditionOperatorEnum::WITHIN_CIRCLE:
                return $this->compileWithinCircle($this->createSingleRef($criteria, $criteria->subField), $value);
            case ConditionOperatorEnum::WITHIN_RECTANGLE:
                return $this->compileWithinRectangle($this->createSingleRef($criteria, $criteria->subField), $value);
            case ConditionOperatorEnum::CONTAINS:
            case ConditionOperatorEnum::NOT_CONTAINS:
            case ConditionOperatorEnum::ENDS_WITH:
            case ConditionOperatorEnum::NOT_ENDS_WITH:
            case ConditionOperatorEnum::STARTS_WITH:
            case ConditionOperatorEnum::NOT_STARTS_WITH:
                $kind = match ($operator) {
                    ConditionOperatorEnum::CONTAINS, ConditionOperatorEnum::NOT_CONTAINS => 'contains',
                    ConditionOperatorEnum::ENDS_WITH, ConditionOperatorEnum::NOT_ENDS_WITH => 'ends',
                    default => 'starts',
                };
                if ('starts' === $kind) {
                    // The value of a prefix query is sent as is
                    if (null === $value || !is_scalar($value)) {
                        throw new UnsupportedEvaluationException(sprintf('Operator "%s" expects a single value', $operator->value));
                    }
                    $needle = self::toKeyword($value);
                } else {
                    // The value is cast to a string in the wildcard pattern
                    if (null !== $value && !is_scalar($value)) {
                        throw new UnsupportedEvaluationException(sprintf('Operator "%s" expects a single value', $operator->value));
                    }
                    $needle = (string) $value;
                }
                $refs = $this->createLocaleRefs($criteria, $criteria->rawSubField);
                $caseInsensitive = $criteria->caseInsensitive;
                $patterns = array_map(fn (array $ref): \Closure => $this->compilePattern($ref, $kind, $needle, $caseInsensitive), $refs);

                return $negate(self::any($patterns), in_array($operator, [ConditionOperatorEnum::NOT_CONTAINS, ConditionOperatorEnum::NOT_STARTS_WITH, ConditionOperatorEnum::NOT_ENDS_WITH], true));
            default:
                throw new BadRequestHttpException(sprintf('Operator "%s" not implemented', $operator->value));
        }
    }

    /**
     * Mirror of AQLToESQuery::createEntityBranchQuery(): an entity matches the values of its descendants too,
     * through the branch field (the ID field keeps matching the values indexed before it).
     */
    private function compileEntityBranch(ResolvedCriteria $criteria, mixed $value): \Closure
    {
        $refs = [
            ...$this->createLocaleRefs($criteria, $criteria->subField),
            ...$this->createLocaleRefs($criteria, EntityAttributeType::BRANCH_FIELD),
        ];
        $matchAny = fn (array $ids): \Closure => self::any(array_map(fn (array $ref): \Closure => $this->compileTerms($ref, $ids), $refs));
        $ids = array_values(array_map('strval', (array) $value));

        if (ConditionOperatorEnum::HAS_ALL_OF === $criteria->operator) {
            $all = array_map(fn (string $id): \Closure => $matchAny([$id]), $ids);

            return static fn (array $doc): bool => array_all($all, static fn (\Closure $m): bool => $m($doc));
        }

        return $matchAny($ids);
    }

    /**
     * A field of the document: the field queried ("path"), where its values are read from the source
     * ("source") and its mapping (null when unknown: the field cannot be evaluated if it has values).
     *
     * @return array{path: string, source: string, mapping: ?array}
     */
    private function createRef(AttributeTypeInterface $type, ?BuiltInAttributeInterface $builtInAttribute, string $field, ?string $subField): array
    {
        if (null !== $builtInAttribute) {
            $segments = explode('.', $field);
            $name = array_shift($segments);
            $mapping = self::BUILT_IN_MAPPINGS[$name] ?? throw new UnsupportedEvaluationException(sprintf('Built-in field "%s" cannot be evaluated outside of Elasticsearch', $builtInAttribute::getKey()));
            $root = $name;
        } else {
            $segments = explode('.', $field);
            if (count($segments) < 3 || AttributeInterface::ATTRIBUTES_FIELD !== $segments[0]) {
                throw new UnsupportedEvaluationException(sprintf('Unsupported field "%s"', $field));
            }
            $root = implode('.', array_slice($segments, 0, 3));
            $mapping = $this->getAttributeMapping($type, $segments[1]);
            $segments = array_slice($segments, 3);
        }

        if (null !== $subField) {
            $segments = [...$segments, ...explode('.', $subField)];
        }

        $source = $root;
        foreach ($segments as $segment) {
            if (null === $mapping) {
                break;
            }
            if (isset($mapping['properties'][$segment])) {
                $mapping = $mapping['properties'][$segment];
                $source .= '.'.$segment;
            } elseif (isset($mapping['fields'][$segment])) {
                // A multi-field indexes the value of its parent
                $mapping = $mapping['fields'][$segment];
            } else {
                $mapping = null;
            }
        }

        if (null !== $builtInAttribute && null === $mapping) {
            throw new UnsupportedEvaluationException(sprintf('Built-in field "%s" cannot be evaluated outside of Elasticsearch', $builtInAttribute::getKey()));
        }

        return [
            'path' => $field.(null !== $subField ? '.'.$subField : ''),
            'source' => $source,
            'mapping' => $mapping,
        ];
    }

    /**
     * The mapping IndexMappingTemplatesMaker gives an attribute field in a locale, null when no
     * template applies (dynamic mapping).
     */
    private function getAttributeMapping(AttributeTypeInterface $type, string $locale): ?array
    {
        if ($type->isMappingLocaleAware()) {
            if (AttributeInterface::NO_LOCALE !== $locale && !isset(AttributeLocaleInterface::LOCALES[$locale])) {
                return null;
            }
        } else {
            $locale = AttributeInterface::NO_LOCALE;
        }

        $mapping = $type->getElasticSearchMapping($locale);
        if (null === $mapping) {
            return null;
        }

        return array_merge(['type' => $type->getElasticSearchType()], $mapping);
    }

    private function createSingleRef(ResolvedCriteria $criteria, ?string $subField): array
    {
        if (str_contains($criteria->field, '*')) {
            throw new UnsupportedEvaluationException(sprintf('Field pattern "%s" is not supported with operator "%s"', $criteria->field, $criteria->operator->value));
        }

        return $this->createRef($criteria->type, $criteria->builtInAttribute, $criteria->field, $subField);
    }

    /**
     * The fields a "*" locale spans, as AQLToESQuery::yieldShouldQuery() does.
     *
     * @return list<array{path: string, source: string, mapping: ?array}>
     */
    private function createLocaleRefs(ResolvedCriteria $criteria, ?string $subField): array
    {
        if (!str_contains($criteria->field, '*')) {
            return [$this->createRef($criteria->type, $criteria->builtInAttribute, $criteria->field, $subField)];
        }

        $refs = [];
        foreach ([...$criteria->locales, AttributeInterface::NO_LOCALE] as $locale) {
            $refs[] = $this->createRef($criteria->type, $criteria->builtInAttribute, str_replace('*', $locale, $criteria->field), $subField);
        }

        return $refs;
    }

    /**
     * The fields a "*" pattern matches in the document, as a multi_match query on a field pattern.
     *
     * @return list<array{path: string, source: string, mapping: ?array}>
     */
    private function getPatternRefs(array $doc, ResolvedCriteria $criteria, ?string $subField): array
    {
        if (!str_contains($criteria->field, '*')) {
            return [$this->createRef($criteria->type, $criteria->builtInAttribute, $criteria->field, $subField)];
        }

        $segments = explode('.', $criteria->field);
        if (null !== $criteria->builtInAttribute || 3 !== count($segments) || '*' !== $segments[1] || str_contains($segments[2], '*')) {
            throw new UnsupportedEvaluationException(sprintf('Unsupported field pattern "%s"', $criteria->field));
        }

        $refs = [];
        foreach ($this->readValues($doc, $segments[0]) as $locales) {
            if (!is_array($locales)) {
                continue;
            }
            foreach ($locales as $locale => $fields) {
                if (is_array($fields) && array_key_exists($segments[2], $fields)) {
                    $refs[(string) $locale] = $this->createRef($criteria->type, null, sprintf('%s.%s.%s', $segments[0], $locale, $segments[2]), $subField);
                }
            }
        }

        return array_values($refs);
    }

    private function getKind(array $ref): string
    {
        $mapping = $ref['mapping'] ?? throw new UnsupportedEvaluationException(sprintf('The mapping of field "%s" is not known', $ref['path']));

        return match ($mapping['type'] ?? 'object') {
            'keyword' => 'keyword',
            'text' => 'collection_path' === ($mapping['analyzer'] ?? null) ? 'path' : 'text',
            'long', 'integer', 'short', 'byte' => 'number',
            'boolean' => 'boolean',
            'date' => 'date',
            'geo_point' => 'geo',
            'ip' => 'ip',
            'object' => 'object',
            self::ID_FIELD => self::ID_FIELD,
            default => throw new UnsupportedEvaluationException(sprintf('Fields of type "%s" are not supported', $mapping['type'])),
        };
    }

    /**
     * The values of the field, null when it has none, which do not depend on its mapping.
     */
    private function readFieldValues(array $doc, array $ref): array
    {
        $values = $this->readValues($doc, $ref['source']);
        if (!empty($values) && null === $ref['mapping']) {
            throw new UnsupportedEvaluationException(sprintf('The mapping of field "%s" is not known', $ref['path']));
        }

        return $values;
    }

    /**
     * The values of a keyword field that are indexed: those not exceeding ignore_above.
     *
     * @return string[]
     */
    private function readIndexedKeywords(array $doc, array $ref): array
    {
        $values = array_map(self::toKeyword(...), $this->readFieldValues($doc, $ref));
        if (null !== $ignoreAbove = $ref['mapping']['ignore_above'] ?? null) {
            $values = array_values(array_filter($values, static fn (string $v): bool => self::utf16Length($v) <= $ignoreAbove));
        }

        return $values;
    }

    private function exists(array $doc, array $ref): bool
    {
        $values = $this->readFieldValues($doc, $ref);
        if (empty($values)) {
            return false;
        }

        switch ($this->getKind($ref)) {
            case 'keyword':
                if (count($this->readIndexedKeywords($doc, $ref)) !== count($values)) {
                    throw new UnsupportedEvaluationException(sprintf('The existence of the values of "%s" beyond ignore_above is not reproduced', $ref['path']));
                }

                return true;
            case 'text':
                foreach ($values as $value) {
                    if (empty(DocumentTextAnalyzer::indexTerms($this->getAnalyzer($ref), [self::toKeyword($value)]))) {
                        throw new UnsupportedEvaluationException(sprintf('The existence of a value of "%s" without any term is not reproduced', $ref['path']));
                    }
                }

                return true;
            case 'path':
                if (in_array('', array_map(self::toKeyword(...), $values), true)) {
                    throw new UnsupportedEvaluationException(sprintf('The existence of an empty path in "%s" is not reproduced', $ref['path']));
                }

                return true;
            default:
                return true;
        }
    }

    private function getAnalyzer(array $ref): string
    {
        $mapping = $ref['mapping'];
        $analyzer = $mapping['analyzer'] ?? DocumentTextAnalyzer::STANDARD;
        if (($mapping['search_analyzer'] ?? $analyzer) !== $analyzer) {
            throw new UnsupportedEvaluationException(sprintf('The search analyzer of "%s" is not reproduced', $ref['path']));
        }

        return $analyzer;
    }

    /**
     * A terms (or term) query on one field.
     *
     * @param array $values the terms
     */
    private function compileTerms(array $ref, array $values): \Closure
    {
        $kind = null !== $ref['mapping'] ? $this->getKind($ref) : null;
        $tests = array_values(array_filter(array_map(fn (mixed $v): ?\Closure => $this->compileTerm($kind, $v, $ref['mapping']['type'] ?? null), $values)));

        return function (array $doc) use ($ref, $kind, $tests): bool {
            $values = $this->readFieldValues($doc, $ref);
            if (empty($values) || empty($tests)) {
                return false;
            }
            $values = 'keyword' === $kind ? $this->readIndexedKeywords($doc, $ref) : $this->normalizeDocumentValues($kind, $ref, $values);

            return array_any($values, static fn (mixed $v): bool => array_any($tests, static fn (\Closure $t): bool => $t($v)));
        };
    }

    /**
     * The values of a document field as the term queries compare them: numbers as indexed, terms of
     * the analyzed fields.
     */
    private function normalizeDocumentValues(?string $kind, array $ref, array $values): array
    {
        return match ($kind) {
            'number' => array_map(fn (mixed $v): int => $this->toIndexedInteger($v, $ref), $values),
            'boolean' => array_map(static fn (mixed $v): bool => is_bool($v) ? $v : throw new UnsupportedEvaluationException(sprintf('Invalid boolean in "%s"', $ref['path'])), $values),
            'path' => array_merge(...array_map(static fn (mixed $v): array => DocumentTextAnalyzer::pathHierarchyTokens(self::toKeyword($v)), $values)),
            'text' => array_map(strval(...), array_keys(DocumentTextAnalyzer::indexTerms($this->getAnalyzer($ref), array_map(self::toKeyword(...), $values)))),
            'ip' => array_map(static fn (mixed $v): string => is_string($v) && false !== ($ip = @inet_pton($v)) ? $ip : throw new UnsupportedEvaluationException(sprintf('Invalid IP in "%s"', $ref['path'])), $values),
            self::ID_FIELD => array_map(self::toKeyword(...), $values),
            default => throw new UnsupportedEvaluationException(sprintf('Term queries on "%s" are not supported', $ref['path'])),
        };
    }

    /**
     * One term of a term query, null when it matches nothing (a decimal on an integer field).
     *
     * @return (\Closure(mixed): bool)|null
     */
    private function compileTerm(?string $kind, mixed $value, ?string $esType = null): ?\Closure
    {
        if (null === $value || is_array($value) || is_object($value)) {
            throw new UnsupportedEvaluationException('A term query expects a single non-null value');
        }

        switch ($kind) {
            case null:
                // An unmapped field without values matches nothing
                return static fn (mixed $v): bool => false;
            case 'keyword':
            case 'path':
            case 'text':
            case self::ID_FIELD:
                $expected = self::toKeyword($value);

                return static fn (mixed $v): bool => $v === $expected;
            case 'number':
                if (is_bool($value) || !is_numeric($value)) {
                    throw new UnsupportedEvaluationException(sprintf('Invalid number "%s"', self::toKeyword($value)));
                }
                if (floor((float) $value) !== (float) $value) {
                    return null;
                }
                $number = (float) $value;
                $range = self::INTEGER_RANGES[$esType ?? 'long'] ?? self::INTEGER_RANGES['long'];
                if ($number < $range[0] || $number > $range[1]) {
                    throw new UnsupportedEvaluationException(sprintf('Value "%s" is out of range', self::toKeyword($value)));
                }
                self::assertExactFloat($number, $value);
                $integer = (int) $number;

                return static fn (mixed $v): bool => $v === $integer;
            case 'boolean':
                $bool = match (true) {
                    is_bool($value) => $value,
                    'true' === $value => true,
                    'false' === $value => false,
                    default => throw new UnsupportedEvaluationException(sprintf('Invalid boolean value "%s"', self::toKeyword($value))),
                };

                return static fn (mixed $v): bool => $v === $bool;
            case 'ip':
                $expected = is_string($value) ? @inet_pton($value) : false;
                if (false === $expected) {
                    throw new UnsupportedEvaluationException(sprintf('Invalid IP value "%s"', self::toKeyword($value)));
                }

                return static fn (mixed $v): bool => $v === $expected;
            default:
                throw new UnsupportedEvaluationException(sprintf('Term queries on "%s" fields are not supported', $kind));
        }
    }

    /**
     * Mirror of AQLToESQuery::createTermQuery(): a term query, a multi_match one on a field pattern.
     */
    private function compileTermQuery(ResolvedCriteria $criteria, mixed $value): \Closure
    {
        if (!str_contains($criteria->field, '*')) {
            return $this->compileTerms($this->createRef($criteria->type, $criteria->builtInAttribute, $criteria->field, $criteria->subField), [$value]);
        }

        $this->compileTerm('keyword', $value);

        return function (array $doc) use ($criteria, $value): bool {
            foreach ($this->getPatternRefs($doc, $criteria, $criteria->subField) as $ref) {
                if ('keyword' !== $this->getKind($ref)) {
                    throw new UnsupportedEvaluationException(sprintf('A multi_match query on "%s" is not supported', $ref['path']));
                }
                if ($this->compileTerms($ref, [$value])($doc)) {
                    return true;
                }
            }

            return false;
        };
    }

    /**
     * Mirror of AQLToESQuery::createMatchQuery(): a match query with the AND operator, a multi_match
     * one on a field pattern (the AND operator then applies to each field).
     */
    private function compileMatchQuery(ResolvedCriteria $criteria, mixed $value): \Closure
    {
        if (null === $value || !is_scalar($value)) {
            throw new UnsupportedEvaluationException('A match query expects a single non-null value');
        }
        $query = self::toKeyword($value);

        return function (array $doc) use ($criteria, $query): bool {
            foreach ($this->getPatternRefs($doc, $criteria, $criteria->subField) as $ref) {
                $values = $this->readFieldValues($doc, $ref);
                if (empty($values)) {
                    continue;
                }
                $matches = match ($this->getKind($ref)) {
                    'keyword' => in_array($query, $this->readIndexedKeywords($doc, $ref), true),
                    'text' => DocumentTextAnalyzer::matches(
                        $this->getAnalyzer($ref),
                        $query,
                        DocumentTextAnalyzer::indexTerms($this->getAnalyzer($ref), array_map(self::toKeyword(...), $values)),
                    ),
                    default => throw new UnsupportedEvaluationException(sprintf('A match query on "%s" is not supported', $ref['path'])),
                };
                if ($matches) {
                    return true;
                }
            }

            return false;
        };
    }

    /**
     * A prefix ("starts") or wildcard ("contains", "ends") query on a keyword field.
     */
    private function compilePattern(array $ref, string $kind, string $needle, bool $caseInsensitive): \Closure
    {
        if (null !== $ref['mapping'] && 'keyword' !== $this->getKind($ref)) {
            throw new UnsupportedEvaluationException(sprintf('Pattern queries on "%s" are not supported', $ref['path']));
        }
        $needleChars = mb_str_split($needle);
        if ($caseInsensitive) {
            self::assertCaseInsensitiveSupport($needle);
        }

        return function (array $doc) use ($ref, $kind, $needle, $needleChars, $caseInsensitive): bool {
            foreach ($this->readIndexedKeywords($doc, $ref) as $value) {
                if (!$caseInsensitive) {
                    $matches = match ($kind) {
                        'contains' => str_contains($value, $needle),
                        'ends' => str_ends_with($value, $needle),
                        default => str_starts_with($value, $needle),
                    };
                } else {
                    self::assertCaseInsensitiveSupport($value);
                    $chars = mb_str_split($value);
                    $n = count($needleChars);
                    $offsets = match ($kind) {
                        'contains' => range(0, max(-1, count($chars) - $n)),
                        'ends' => [count($chars) - $n],
                        default => [0],
                    };
                    $matches = array_any($offsets, static fn (int $offset): bool => $offset >= 0
                        && $offset + $n <= count($chars)
                        && array_all($needleChars, static fn (string $c, int $i): bool => self::caseInsensitiveCharMatches($c, $chars[$offset + $i])));
                }
                if ($matches) {
                    return true;
                }
            }

            return false;
        };
    }

    /**
     * The case insensitive prefix and wildcard queries of Elasticsearch accept, for each ASCII letter
     * of the pattern, itself or its other case; the other characters match exactly. The characters
     * that a Unicode case folding would also match with an ASCII letter are refused.
     */
    private static function assertCaseInsensitiveSupport(string $value): void
    {
        if (preg_match('/[\x{DF}\x{B5}\x{130}\x{131}\x{17F}\x{212A}\x{212B}\x{1E9E}\x{39C}\x{3BC}]/u', $value)) {
            throw new UnsupportedEvaluationException('The case insensitive match of these characters is not reproduced');
        }
    }

    private static function caseInsensitiveCharMatches(string $patternChar, string $char): bool
    {
        return $patternChar === $char || (ctype_alpha($patternChar) && strtolower($patternChar) === strtolower($char));
    }

    /**
     * @param array<'gt'|'gte'|'lt'|'lte', float> $bounds
     */
    private function compileRange(array $ref, array $bounds): \Closure
    {
        $kind = $this->getKind($ref);
        if (!in_array($kind, ['number', 'date'], true)) {
            throw new UnsupportedEvaluationException(sprintf('Range queries on "%s" are not supported', $ref['path']));
        }

        return function (array $doc) use ($ref, $kind, $bounds): bool {
            foreach ($this->readFieldValues($doc, $ref) as $v) {
                $n = 'date' === $kind ? $this->parseDocumentDate($v, $ref) : (float) $this->toIndexedInteger($v, $ref);
                if (array_all($bounds, static fn (float $b, string $op): bool => match ($op) {
                    'gt' => $n > $b,
                    'gte' => $n >= $b,
                    'lt' => $n < $b,
                    default => $n <= $b,
                })) {
                    return true;
                }
            }

            return false;
        };
    }

    /**
     * A bound of a range. An integer field compares exactly to a decimal bound. A date is compared in
     * milliseconds, its missing components rounded up for a "gt" or "lte" bound, as Elasticsearch does.
     */
    private function compileBound(array $ref, mixed $value, bool $roundUp, bool $strictDateTime = false): float
    {
        if ('date' !== $this->getKind($ref)) {
            if (is_bool($value) || !is_numeric($value)) {
                throw new UnsupportedEvaluationException(sprintf('Invalid number "%s"', is_scalar($value) ? self::toKeyword($value) : get_debug_type($value)));
            }

            return self::assertExactFloat((float) $value, $value);
        }

        if ($strictDateTime && (!is_string($value)
            || !preg_match('#^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{1,9}(?:Z|[+-]\d{2}:?\d{2})$#', $value))) {
            throw new UnsupportedEvaluationException(sprintf('Date "%s" does not match the strict_date_time format', is_scalar($value) ? self::toKeyword($value) : get_debug_type($value)));
        }

        if (is_int($value)) {
            return (float) $value;
        }

        if (!is_string($value)) {
            throw new UnsupportedEvaluationException(sprintf('Invalid date type "%s"', get_debug_type($value)));
        }

        if (preg_match('/^\d+$/', $value)) {
            return (float) $value;
        }

        $ms = self::parseDate($value) ?? throw new UnsupportedEvaluationException(sprintf('Invalid date value "%s"', $value));
        if ($roundUp) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $ms += 86_400_000 - 1;
            } elseif (!str_contains($value, '.')) {
                $ms += 999;
            }
        }

        return $ms;
    }

    /**
     * A date as the date field indexes it (strict_date_optional_time||epoch_millis), in milliseconds.
     */
    private function parseDocumentDate(mixed $value, array $ref): float
    {
        if (is_int($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            if (preg_match('/^\d+$/', $value)) {
                return (float) $value;
            }
            if (null !== $ms = self::parseDate($value)) {
                return $ms;
            }
        }

        throw new UnsupportedEvaluationException(sprintf('Invalid date in "%s"', $ref['path']));
    }

    private static function parseDate(string $value): ?float
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}(?:T\d{2}:\d{2}(?::\d{2}(?:\.\d{1,9})?)?(?:Z|[+-]\d{2}:?\d{2})?)?$/', $value)) {
            return null;
        }

        try {
            $date = new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
        } catch (\Exception) {
            return null;
        }

        // The date fields have a millisecond resolution: a finer bound is truncated
        return (float) $date->format('Uv');
    }

    /**
     * The value an integer field indexes: decimals truncated (coerce).
     */
    private function toIndexedInteger(mixed $value, array $ref): int
    {
        if (is_bool($value) || !is_numeric($value)) {
            throw new UnsupportedEvaluationException(sprintf('Invalid number in "%s"', $ref['path']));
        }

        $range = self::INTEGER_RANGES[$ref['mapping']['type'] ?? 'long'] ?? self::INTEGER_RANGES['long'];
        if (is_int($value)) {
            if ($value < $range[0] || $value > $range[1]) {
                throw new UnsupportedEvaluationException(sprintf('Number out of range in "%s"', $ref['path']));
            }

            return $value;
        }

        $number = (float) $value;
        if ($number < $range[0] || $number > $range[1] || is_nan($number)) {
            throw new UnsupportedEvaluationException(sprintf('Number out of range in "%s"', $ref['path']));
        }

        return (int) self::assertExactFloat($number, $value);
    }

    /**
     * Refuses a number from 2^53 on, where distinct integers share the same float.
     */
    private static function assertExactFloat(float $number, mixed $value): float
    {
        if (abs($number) >= self::MAX_EXACT_FLOAT_INTEGER) {
            throw new UnsupportedEvaluationException(sprintf('The exact comparison of "%s" is not reproduced', is_scalar($value) ? self::toKeyword($value) : get_debug_type($value)));
        }

        return $number;
    }

    private function compileWithinCircle(array $ref, mixed $value): \Closure
    {
        if ('geo' !== $this->getKind($ref) || !is_array($value) || 3 !== count($value)
            || !self::isNumber($value[0] ?? null) || !self::isNumber($value[1] ?? null)) {
            throw new UnsupportedEvaluationException('Invalid WITHIN CIRCLE condition');
        }
        [$lat, $lon] = self::assertPoint((float) $value[0], (float) $value[1]);
        $distance = self::parseDistance($value[2]);

        return function (array $doc) use ($ref, $lat, $lon, $distance): bool {
            foreach ($this->readFieldValues($doc, $ref) as $point) {
                [$pLat, $pLon] = self::toPoint($point, $ref);
                if (self::arcDistance($lat, $lon, $pLat, $pLon) <= $distance) {
                    return true;
                }
            }

            return false;
        };
    }

    private function compileWithinRectangle(array $ref, mixed $value): \Closure
    {
        if ('geo' !== $this->getKind($ref) || !is_array($value) || 4 !== count($value)
            || !array_all($value, self::isNumber(...))) {
            throw new UnsupportedEvaluationException('Invalid WITHIN RECTANGLE condition');
        }
        [$top, $left] = self::assertPoint((float) $value[0], (float) $value[1]);
        [$bottom, $right] = self::assertPoint((float) $value[2], (float) $value[3]);
        if ($top < $bottom) {
            throw new UnsupportedEvaluationException('The top of the rectangle is below its bottom');
        }

        return function (array $doc) use ($ref, $top, $left, $bottom, $right): bool {
            foreach ($this->readFieldValues($doc, $ref) as $point) {
                [$lat, $lon] = self::toPoint($point, $ref);
                $inLon = $left <= $right ? ($lon >= $left && $lon <= $right) : ($lon >= $left || $lon <= $right);
                if ($lat <= $top && $lat >= $bottom && $inLon) {
                    return true;
                }
            }

            return false;
        };
    }

    /**
     * @return array{float, float}
     */
    private static function assertPoint(float $lat, float $lon): array
    {
        if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
            throw new UnsupportedEvaluationException('Invalid coordinates');
        }

        return [$lat, $lon];
    }

    /**
     * @return array{float, float}
     */
    private static function toPoint(mixed $point, array $ref): array
    {
        if (is_array($point) && isset($point['lat'], $point['lon']) && is_numeric($point['lat']) && is_numeric($point['lon'])) {
            return self::assertPoint((float) $point['lat'], (float) $point['lon']);
        }

        throw new UnsupportedEvaluationException(sprintf('Unsupported geo point in "%s"', $ref['path']));
    }

    private static function parseDistance(mixed $distance): float
    {
        if (self::isNumber($distance)) {
            return (float) $distance;
        }

        if (is_string($distance) && preg_match('/^\s*(\d+(?:\.\d+)?)\s*([a-zA-Z]*)\s*$/', $distance, $m)) {
            $unit = strtolower($m[2] ?: 'm');
            if (isset(self::DISTANCE_UNITS[$unit])) {
                return (float) $m[1] * self::DISTANCE_UNITS[$unit];
            }
        }

        throw new UnsupportedEvaluationException(sprintf('Invalid distance "%s"', is_scalar($distance) ? self::toKeyword($distance) : get_debug_type($distance)));
    }

    private static function arcDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $h = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return 2 * self::EARTH_RADIUS * asin(min(1.0, sqrt($h)));
    }

    /**
     * Mirror of the createFilterQuery() of {@see DeletedBuiltInAttribute} and {@see AssetStatusBuiltInAttribute}.
     */
    private function compileCustomBuiltIn(CustomFilterQueryBuiltInAttributeInterface $builtInAttribute, mixed $value, ConditionOperatorEnum $operator, array $options): \Closure
    {
        if ($builtInAttribute instanceof DeletedBuiltInAttribute) {
            $equals = fn (mixed $v): \Closure => $this->compileDeletedEquals($v, $options);
        } elseif ($builtInAttribute instanceof AssetStatusBuiltInAttribute) {
            $equals = fn (mixed $v): \Closure => $this->compileStatusEquals($v, $options);
        } else {
            throw new UnsupportedEvaluationException(sprintf('Built-in field "%s" cannot be evaluated outside of Elasticsearch', $builtInAttribute::getKey()));
        }

        switch ($operator) {
            case ConditionOperatorEnum::EQUALS:
                return $equals($value);
            case ConditionOperatorEnum::NOT_EQUALS:
                if ($builtInAttribute instanceof DeletedBuiltInAttribute) {
                    if (null !== $value) {
                        $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    }

                    return $equals(!$value);
                }
                $status = AssetStatusAttributeType::normalizeInput($value)
                    ?? throw new UnsupportedEvaluationException('A term query expects a single non-null value');
                $access = AssetStatusEnum::Accepted === $status ? $this->compileQuarantineAccess($options) : null;

                return static fn (array $doc): bool => !in_array($status->value, self::readIntegers($doc, 'status'), true) && (null === $access || $access($doc));
            case ConditionOperatorEnum::IN:
                return self::any(array_map($equals, self::assertList($value)));
            case ConditionOperatorEnum::NOT_IN:
                self::assertList($value);

                // A bool query requiring one "should" clause without any matches nothing
                return static fn (array $doc): bool => false;
            default:
                throw new \InvalidArgumentException(sprintf('Operator %s is not supported for assetStatus field', $operator->value));
        }
    }

    private function compileDeletedEquals(mixed $value, array $options): \Closure
    {
        if (null !== $value) {
            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        $isNotDeleted = static fn (array $doc): bool => in_array(false, self::readBooleans($doc, 'deleted'), true)
            && in_array(false, self::readBooleans($doc, 'collectionDeleted'), true);
        if (false === $value) {
            return $isNotDeleted;
        }

        $userId = $options['userId'] ?? null;
        $groupIds = $options['groupIds'] ?? [];
        $canDelete = function (array $doc) use ($userId, $groupIds): bool {
            $ownerIds = $this->readKeywords($doc, 'ownerId');
            if (null === $userId) {
                return in_array(AbstractSearch::NO_AUTH, $ownerIds, true);
            }

            return in_array($userId, $ownerIds, true)
                || !empty(array_intersect([$userId, AccessControlEntryInterface::USER_WILDCARD], $this->readKeywords($doc, 'deleteUsers')))
                || !empty(array_intersect([...$groupIds, AccessControlEntryInterface::USER_WILDCARD], $this->readKeywords($doc, 'deleteGroups')));
        };

        if (null === $value) {
            return static fn (array $doc): bool => $isNotDeleted($doc) || $canDelete($doc);
        }

        return static fn (array $doc): bool => $canDelete($doc)
            && (in_array(true, self::readBooleans($doc, 'deleted'), true) || in_array(true, self::readBooleans($doc, 'collectionDeleted'), true));
    }

    private function compileStatusEquals(mixed $value, array $options): \Closure
    {
        $status = AssetStatusAttributeType::normalizeInput($value)
            ?? throw new UnsupportedEvaluationException('A term query expects a single non-null value');
        $access = in_array($status, [AssetStatusEnum::Quarantined, AssetStatusEnum::Pending], true) ? $this->compileQuarantineAccess($options) : null;

        return static fn (array $doc): bool => in_array($status->value, self::readIntegers($doc, 'status'), true) && (null === $access || $access($doc));
    }

    private function compileQuarantineAccess(array $options): \Closure
    {
        $userId = $options['userId'] ?? null;
        if (empty($userId)) {
            return fn (array $doc): bool => in_array(AbstractSearch::NO_AUTH, $this->readKeywords($doc, 'ownerId'), true);
        }
        $groupIds = $options['groupIds'] ?? [];

        return fn (array $doc): bool => in_array($userId, $this->readKeywords($doc, 'ownerId'), true)
            || !empty(array_intersect([$userId, AccessControlEntryInterface::USER_WILDCARD], $this->readKeywords($doc, 'quarantineUsers')))
            || !empty(array_intersect([...$groupIds, AccessControlEntryInterface::USER_WILDCARD], $this->readKeywords($doc, 'quarantineGroups')));
    }

    /**
     * Mirror of AQLToESQuery::visitCriteriaWithScripting(): a criteria comparing fields, or values
     * computed from fields, evaluated as the Painless script would be on the doc values.
     */
    private function compileScriptedCriteria(array $fieldClusters, array $data, array $options): \Closure
    {
        $scriptFields = new \ArrayObject();
        $groups = $this->compileScriptNode($data, $fieldClusters, $options, $scriptFields);
        if (empty($groups)) {
            throw new InvalidAQLException('The fields of the condition cannot be compared');
        }

        $matchers = array_map(fn (ClusterGroup $group): \Closure => $this->wrapCluster(
            function (array $doc) use ($group, $scriptFields): bool {
                // An asset missing a value read by the expression matches no operator, "!=" included
                foreach ($group->getScriptFields() as $fieldName) {
                    if (empty($this->readDocValues($doc, $scriptFields[$fieldName]))) {
                        return false;
                    }
                }

                return true === ($group->getItem())($doc);
            },
            $group,
        ), $groups);

        return self::any($matchers);
    }

    /**
     * Mirror of AQLToESQuery::expressionToScript().
     *
     * @param \ArrayObject<string, array> $scriptFields the fields read by the scripts, by name
     *
     * @return ClusterGroup[] groups whose item is a \Closure(array $doc): mixed
     */
    private function compileScriptNode(mixed $node, array $fieldClusters, array $options, \ArrayObject $scriptFields): array
    {
        if ($this->resolver->isResolvableValue($node)) {
            $constant = self::assertScriptParam($this->resolver->resolveValue($node));

            return [new ClusterGroup(static fn (): mixed => $constant, true)];
        }

        if (!is_array($node)) {
            throw new InvalidAQLException('Unsupported value in script conditions');
        }

        $type = $node['type'] ?? null;
        if (null === $type) {
            if (!isset($node['field'])) {
                throw new InvalidAQLException('Unsupported value in script conditions');
            }

            $fields = $this->resolver->getFieldNames($fieldClusters, $node['field'], $options);
            if (empty($fields)) {
                throw new BadRequestHttpException(sprintf('Field "%s" not found', $node['field']));
            }

            $scripts = [];
            foreach ($fields as $field) {
                foreach ($this->resolver->resolveScriptFieldNames($field, $options) as $locale => $fieldName) {
                    $scriptFields[$fieldName] = $this->createScriptFieldRef($field, $fieldName);
                    $scripts[] = $field
                        ->convert(fn (array $doc): mixed => $this->readDocValues($doc, $scriptFields[$fieldName])[0] ?? null)
                        ->withFieldLocale(is_string($locale) ? $locale : null)
                        ->withScriptField($fieldName);
                }
            }

            return $scripts;
        }

        switch ($type) {
            case 'criteria':
                $operator = ConditionOperatorEnum::tryFrom($node['operator']);
                if (!in_array($operator, [
                    ConditionOperatorEnum::EQUALS,
                    ConditionOperatorEnum::NOT_EQUALS,
                    ConditionOperatorEnum::GT,
                    ConditionOperatorEnum::GTE,
                    ConditionOperatorEnum::LT,
                    ConditionOperatorEnum::LTE,
                ], true)) {
                    throw new BadRequestHttpException(sprintf('Unsupported operator "%s" in script conditions', $node['operator']));
                }
                $lefts = $this->compileScriptNode($node['leftOperand'], $fieldClusters, $options, $scriptFields);
                $rights = $this->compileScriptNode($node['rightOperand'] ?? null, $fieldClusters, $options, $scriptFields);

                return ClusterGroup::mix(
                    $lefts,
                    $rights,
                    static fn (\Closure $l, \Closure $r): \Closure => static fn (array $doc): bool => self::compareScriptValues($l($doc), $operator, $r($doc)),
                );
            case 'value_expression':
                $operator = ExpressionOperatorEnum::tryFrom($node['operator'])
                    ?? throw new BadRequestHttpException(sprintf('Unsupported operator "%s"', $node['operator']));
                if (ExpressionOperatorEnum::DIVIDE === $operator
                    && $this->resolver->isResolvableValue($node['rightOperand'])
                    && 0 == $this->resolver->resolveValue($node['rightOperand'])
                ) {
                    throw new InvalidAQLException('Division by zero');
                }
                $lefts = $this->compileScriptNode($node['leftOperand'], $fieldClusters, $options, $scriptFields);
                $rights = $this->compileScriptNode($node['rightOperand'], $fieldClusters, $options, $scriptFields);

                return ClusterGroup::mix(
                    $lefts,
                    $rights,
                    static fn (\Closure $l, \Closure $r): \Closure => static fn (array $doc): int|float => self::computeScriptValues($l($doc), $operator, $r($doc)),
                );
            case 'function_call':
                $functionHandle = $this->resolver->getCheckedFunctionHandle($node);
                $args = [];
                foreach ($node['arguments'] as $i => $arg) {
                    if ($this->resolver->isResolvableValue($arg)) {
                        $constant = self::assertScriptParam($this->resolver->resolveArgument($functionHandle, $node['function'], $i, $arg));
                        $args[] = [new ClusterGroup(static fn (): mixed => $constant, true)];
                    } else {
                        $args[] = $this->compileScriptNode($arg, $fieldClusters, $options, $scriptFields);
                    }
                }

                $mixedArguments = [new ClusterGroup([], true)];
                foreach ($args as $arg) {
                    $arg = array_map(static fn (ClusterGroup $a): ClusterGroup => $a->convert([$a->getItem()]), $arg);
                    $mixedArguments = ClusterGroup::mix($mixedArguments, $arg, static fn (array $l, array $r): array => array_merge($l, $r));
                }

                $scripts = [];
                foreach ($mixedArguments as $mixed) {
                    // The script generation refuses the functions a script cannot call
                    $functionHandle->getScript(array_fill(0, count($mixed->getItem()), 'v'));
                    $argClosures = $mixed->getItem();
                    $scripts[] = $mixed->convert(static fn (array $doc): mixed => self::callScriptFunction(
                        $functionHandle,
                        array_map(static fn (\Closure $a): mixed => $a($doc), $argClosures),
                    ));
                }

                return $scripts;
            case 'parentheses':
                return $this->compileScriptNode($node['expression'], $fieldClusters, $options, $scriptFields);
            default:
                throw new InvalidAQLException(sprintf('Unsupported node type "%s" in script conditions', is_scalar($type) ? $type : get_debug_type($type)));
        }
    }

    private function createScriptFieldRef(ClusterGroup $field, string $fieldName): array
    {
        $item = $field->getItem();
        /** @var BuiltInAttributeInterface|null $builtInAttribute */
        $builtInAttribute = $item['builtInAttribute'] ?? null;
        if ($builtInAttribute instanceof CustomFilterQueryBuiltInAttributeInterface) {
            throw new UnsupportedEvaluationException(sprintf('Built-in field "%s" cannot be evaluated outside of Elasticsearch', $builtInAttribute::getKey()));
        }

        $base = str_replace('{l}', '*', $item['field']);
        $baseSegments = count(explode('.', $base));
        $segments = explode('.', $fieldName);
        $esField = implode('.', array_slice($segments, 0, $baseSegments));
        $subField = count($segments) > $baseSegments ? implode('.', array_slice($segments, $baseSegments)) : null;

        return $this->createRef($item['type'], $builtInAttribute, $esField, $subField);
    }

    /**
     * The doc values of a field, sorted as Elasticsearch sorts them: a script reads the first one.
     */
    private function readDocValues(array $doc, array $ref): array
    {
        $values = $this->readFieldValues($doc, $ref);
        if (empty($values)) {
            return [];
        }

        switch ($this->getKind($ref)) {
            case 'keyword':
                $values = array_values(array_unique($this->readIndexedKeywords($doc, $ref)));
                sort($values, SORT_STRING);

                return $values;
            case 'number':
                $values = array_values(array_unique(array_map(fn (mixed $v): int => $this->toIndexedInteger($v, $ref), $values)));
                sort($values, SORT_NUMERIC);

                return $values;
            case 'boolean':
                $values = array_values(array_unique($this->normalizeDocumentValues('boolean', $ref, $values)));
                sort($values);

                return $values;
            default:
                throw new UnsupportedEvaluationException(sprintf('Scripts reading "%s" are not supported', $ref['path']));
        }
    }

    private static function assertScriptParam(mixed $value): mixed
    {
        if (null !== $value && !is_scalar($value)) {
            throw new UnsupportedEvaluationException(sprintf('Unsupported value of type "%s" in script conditions', get_debug_type($value)));
        }

        return $value;
    }

    /**
     * The Painless "==" compares numbers numerically and other values with equals(); the other
     * comparisons require numbers.
     */
    private static function compareScriptValues(mixed $left, ConditionOperatorEnum $operator, mixed $right): bool
    {
        if (self::isNumber($left) && self::isNumber($right)) {
            return match ($operator) {
                ConditionOperatorEnum::EQUALS => $left == $right,
                ConditionOperatorEnum::NOT_EQUALS => $left != $right,
                ConditionOperatorEnum::GT => $left > $right,
                ConditionOperatorEnum::GTE => $left >= $right,
                ConditionOperatorEnum::LT => $left < $right,
                default => $left <= $right,
            };
        }

        if (null === $left || null === $right) {
            throw new UnsupportedEvaluationException('Null values are not supported in script conditions');
        }

        return match ($operator) {
            ConditionOperatorEnum::EQUALS => $left === $right,
            ConditionOperatorEnum::NOT_EQUALS => $left !== $right,
            default => throw new UnsupportedEvaluationException(sprintf('Cannot compare "%s" with "%s"', get_debug_type($left), get_debug_type($right))),
        };
    }

    private static function computeScriptValues(mixed $left, ExpressionOperatorEnum $operator, mixed $right): int|float
    {
        if (!self::isNumber($left) || !self::isNumber($right)) {
            throw new UnsupportedEvaluationException(sprintf('Cannot compute "%s" %s "%s"', get_debug_type($left), $operator->value, get_debug_type($right)));
        }

        if (ExpressionOperatorEnum::DIVIDE === $operator) {
            if (0 == $right) {
                throw new UnsupportedEvaluationException('A division by zero is not reproduced');
            }

            // The script divides decimals
            return $left * 1.0 / $right;
        }

        $result = match ($operator) {
            ExpressionOperatorEnum::PLUS => $left + $right,
            ExpressionOperatorEnum::MINUS => $left - $right,
            default => $left * $right,
        };
        if (is_int($left) && is_int($right) && !is_int($result)) {
            throw new UnsupportedEvaluationException('An integer overflow is not reproduced');
        }

        return $result;
    }

    /**
     * The functions a script calls, with the arguments their Painless and PHP implementations convert alike.
     */
    private static function callScriptFunction(Function\AQLFunctionInterface $functionHandle, array $args): mixed
    {
        $supported = match (true) {
            $functionHandle instanceof ConcatFunction => array_all($args, static fn (mixed $a): bool => is_string($a) || is_int($a)),
            $functionHandle instanceof SubstringFunction => (is_string($args[0]) && 1 === preg_match('/^[\x00-\x7F]*$/', $args[0]) || is_int($args[0]))
                && is_int($args[1]) && abs($args[1]) <= 2147483647
                && (null === ($args[2] ?? null) || (is_int($args[2]) && abs($args[2]) <= 2147483647)),
            default => false,
        };
        if (!$supported) {
            throw new UnsupportedEvaluationException(sprintf('Function "%s" is not reproduced with these arguments', $functionHandle::getName()));
        }

        return $functionHandle->resolve($args);
    }

    /**
     * The values of a field, lists flattened at every level (Elasticsearch array semantics), nulls removed.
     */
    private function readValues(array $doc, string $path): array
    {
        $nodes = [$doc];
        foreach (explode('.', $path) as $segment) {
            $next = [];
            foreach (self::flatten($nodes) as $node) {
                if (is_array($node) && array_key_exists($segment, $node)) {
                    $next[] = $node[$segment];
                }
            }
            $nodes = $next;
        }

        return array_values(array_filter(self::flatten($nodes), static fn (mixed $v): bool => null !== $v));
    }

    private static function flatten(array $nodes): array
    {
        $flat = [];
        foreach ($nodes as $node) {
            if (is_array($node) && array_is_list($node)) {
                array_push($flat, ...self::flatten($node));
            } else {
                $flat[] = $node;
            }
        }

        return $flat;
    }

    /**
     * @return string[]
     */
    private function readKeywords(array $doc, string $field): array
    {
        return array_map(self::toKeyword(...), $this->readValues($doc, $field));
    }

    /**
     * @return bool[]
     */
    private static function readBooleans(array $doc, string $field): array
    {
        $values = (array) ($doc[$field] ?? []);

        return array_map(static fn (mixed $v): bool => is_bool($v) ? $v : throw new UnsupportedEvaluationException(sprintf('Invalid boolean in "%s"', $field)), array_values(array_filter($values, static fn (mixed $v): bool => null !== $v)));
    }

    /**
     * @return int[]
     */
    private static function readIntegers(array $doc, string $field): array
    {
        $values = (array) ($doc[$field] ?? []);

        return array_map(static fn (mixed $v): int => is_int($v) ? $v : throw new UnsupportedEvaluationException(sprintf('Invalid integer in "%s"', $field)), array_values(array_filter($values, static fn (mixed $v): bool => null !== $v)));
    }

    /**
     * @param \Closure[] $matchers
     */
    private static function any(array $matchers): \Closure
    {
        return static fn (array $doc): bool => array_any($matchers, static fn (\Closure $m): bool => $m($doc));
    }

    private static function assertList(mixed $value): array
    {
        if (!is_array($value)) {
            throw new UnsupportedEvaluationException('A list of values is expected');
        }

        return array_values($value);
    }

    private static function toKeyword(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value), is_string($value) => (string) $value,
            is_float($value) => is_finite($value) && $value === floor($value) && abs($value) < 1e15 ? sprintf('%.1F', $value) : throw new UnsupportedEvaluationException('Decimal values are not reproduced as keywords'),
            $value instanceof \Stringable => (string) $value,
            default => throw new UnsupportedEvaluationException(sprintf('Unsupported value type "%s"', get_debug_type($value))),
        };
    }

    private static function utf16Length(string $value): int
    {
        return intdiv(strlen((string) mb_convert_encoding($value, 'UTF-16LE', 'UTF-8')), 2);
    }

    private static function isNumber(mixed $value): bool
    {
        return is_int($value) || is_float($value);
    }
}
