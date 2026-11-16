<?php

declare(strict_types=1);

namespace App\Tests\Unit\AQL;

use Alchemy\AuthBundle\Repository\UserRepositoryInterface;
use App\Attribute\Type\BooleanAttributeType;
use App\Attribute\Type\DateAttributeType;
use App\Attribute\Type\GeoPointAttributeType;
use App\Attribute\Type\NumberAttributeType;
use App\Attribute\Type\TextAttributeType;
use App\Elasticsearch\AbstractSearch;
use App\Elasticsearch\AQL\AQLParser;
use App\Elasticsearch\AQL\AQLToESQuery;
use App\Elasticsearch\AQL\DateNormalizer;
use App\Elasticsearch\AQL\Function\AQLFunctionRegistry;
use App\Elasticsearch\BuiltInAttribute\AssetStatusBuiltInAttribute;
use App\Elasticsearch\BuiltInAttribute\BuiltInAttributeRegistry;
use App\Elasticsearch\BuiltInAttribute\CollectionBuiltInAttribute;
use App\Elasticsearch\BuiltInAttribute\CreatedAtBuiltInAttribute;
use App\Elasticsearch\BuiltInAttribute\DeletedBuiltInAttribute;
use App\Elasticsearch\BuiltInAttribute\DirectCollectionBuiltInAttribute;
use App\Elasticsearch\BuiltInAttribute\FileFamilyBuiltInAttribute;
use App\Elasticsearch\BuiltInAttribute\OwnerBuiltInAttribute;
use App\Elasticsearch\BuiltInAttribute\SimilarBuiltInAttribute;
use App\Elasticsearch\BuiltInAttribute\WorkspaceBuiltInAttribute;
use App\Elasticsearch\Query\Knn;
use App\Elasticsearch\SimilarAssetSearch;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Repository\Core\AssetRepository;
use App\Tests\Unit\Attribute\Type\AttributeTypeRegistryTestFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Contracts\Service\ServiceLocatorTrait;
use Symfony\Contracts\Service\ServiceProviderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AQLToESQueryTest extends TestCase
{
    /**
     * Root collection.
     */
    private const string COLL_A = '2b2c8b1e-0000-4000-8000-00000000000a';

    /**
     * Child of {@see self::COLL_A}.
     */
    private const string COLL_B = '2b2c8b1e-0000-4000-8000-00000000000b';

    /**
     * A collection the user cannot read.
     */
    private const string COLL_SECRET = '2b2c8b1e-0000-4000-8000-00000000000c';

    private const array COLLECTION_PATHS = [
        self::COLL_A => '/'.self::COLL_A,
        self::COLL_B => '/'.self::COLL_A.'/'.self::COLL_B,
        self::COLL_SECRET => '/'.self::COLL_SECRET,
    ];

    #[DataProvider('getCases')]
    public function testAQLToQuery(string $expression, string|array $expectedQuery, ?string $locale = null): void
    {
        if (is_string($expectedQuery)) {
            $this->expectExceptionMessage($expectedQuery);
        }

        $query = $this->convert($expression, $locale);
        if (!is_string($expectedQuery)) {
            $this->assertEquals($expectedQuery, $query);
        }
    }

    /**
     * Facet buckets of boolean fields are keyed 1/0: they must reach ES as real booleans.
     */
    #[DataProvider('getBooleanCases')]
    public function testBooleanValuesAreSentAsBooleans(string $expression, array $expectedQuery): void
    {
        $this->assertSame($expectedQuery, $this->convert($expression));
    }

    public static function getBooleanCases(): iterable
    {
        yield 'true' => ['bool = true', ['term' => ['attrs._.bool_boolean_s' => true]]];
        yield 'int 1' => ['bool = 1', ['term' => ['attrs._.bool_boolean_s' => true]]];
        yield 'string "0"' => ['bool = "0"', ['term' => ['attrs._.bool_boolean_s' => false]]];
        yield 'list of ints' => ['bool IN (1, 0)', ['terms' => ['attrs._.bool_boolean_s' => [true, false]]]];
    }

    /**
     * A built-in attribute disabled for the user (@owner without the required role) does not
     * exist for them, unless the condition comes from an attribute filter rule.
     */
    #[DataProvider('getDisabledBuiltInCases')]
    public function testDisabledBuiltInAttributeIsUnknownToTheUser(string $expression): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Built-in field "@owner" not found');

        $this->convert($expression);
    }

    public static function getDisabledBuiltInCases(): iterable
    {
        yield 'criteria' => ['@owner = "u1"'];
        yield 'negated' => ['NOT (@owner = "u1")'];
        yield 'script' => ['@owner = number'];
    }

    public function testDisabledBuiltInAttributeAppliesInTrustedConditions(): void
    {
        $this->assertSame(
            ['term' => ['ownerId' => 'u1']],
            $this->convert('@owner = "u1"', options: [AQLToESQuery::OPT_TRUSTED_CONDITIONS => true]),
        );
    }

    /**
     * A collection the user cannot read is answered exactly like an unknown one.
     */
    #[DataProvider('getUnreadableCollectionCases')]
    public function testUnreadableCollectionIsAnsweredLikeAnUnknownOne(string $attribute): void
    {
        $messages = [];
        foreach ([self::COLL_SECRET, '2b2c8b1e-0000-4000-8000-0000000000ff'] as $id) {
            try {
                $this->convert(sprintf('%s = "%s"', $attribute, $id));
                $this->fail('The condition must be refused');
            } catch (BadRequestHttpException $e) {
                $messages[] = $e->getMessage();
            }
        }

        $this->assertStringEndsWith(': Collection not found', $messages[0]);
        $this->assertSame($messages[0], $messages[1]);
    }

    public static function getUnreadableCollectionCases(): iterable
    {
        yield 'recursive' => ['@collection'];
        yield 'direct' => ['@directCollection'];
    }

    /**
     * Attribute filter rules are written by an administrator: they apply whatever the user can read.
     */
    public function testUnreadableCollectionAppliesInTrustedConditions(): void
    {
        $this->assertSame(
            ['term' => ['collectionPaths' => '/'.self::COLL_SECRET]],
            $this->convert(sprintf('@collection = "%s"', self::COLL_SECRET), options: [AQLToESQuery::OPT_TRUSTED_CONDITIONS => true]),
        );
    }

    /**
     * The errors due to the condition itself are user errors (400), whose message does not dump the AST.
     */
    #[DataProvider('getInvalidConditionCases')]
    public function testInvalidConditionIsABadRequest(string $expression, string $expectedMessage, ?string $locale = null): void
    {
        try {
            $this->convert($expression, $locale);
            $this->fail('The condition must be refused');
        } catch (BadRequestHttpException $e) {
            $this->assertSame($expectedMessage, $e->getMessage());
        }
    }

    public static function getInvalidConditionCases(): iterable
    {
        yield 'division by zero' => ['@createdAt > 1 / 0', 'Division by zero'];
        yield 'division by a zero expression' => ['@createdAt > 2 / (1 - 1)', 'Division by zero'];
        yield 'division by zero in a script' => ['number > othernumber / 0', 'Division by zero'];
        yield 'non-numeric operand' => ['@createdAt > "a" + 1', 'Operator "+" expects numbers, got "a"'];
        yield 'boolean operand' => ['@createdAt > true * 2', 'Operator "*" expects numbers, got bool'];
        yield 'malformed interval' => ['@createdAt > DATE_ADD(NOW(), "1 hour")', 'Function "date_add" expects an ISO 8601 interval (e.g. "PT1H"), got "1 hour"'];
        yield 'numeric interval' => ['@createdAt > DATE_SUB(NOW(), 3600)', 'Function "date_sub" expects an ISO 8601 interval (e.g. "PT1H"), got "3600"'];
        yield 'invalid date' => ['@createdAt > DATE_ADD("garbage", "PT1H")', 'Function "date_add" expects a date, got "garbage"'];
        yield 'string substring start' => ['foo = SUBSTRING("hello", "1", 2)', 'Argument "start" of function "SUBSTRING" must be an integer, got "1"'];
        yield 'float substring length' => ['foo = SUBSTRING("hello", 1, 2.5)', 'Argument "length" of function "SUBSTRING" must be an integer, got float'];
        yield 'string substring start in a script' => ['foo = SUBSTRING(foo, "x")', 'Argument "start" of function "SUBSTRING" must be an integer, got "x"', 'fr'];
        yield 'missing argument in a script' => ['foo = SUBSTRING(foo)', 'Function "SUBSTRING" requires at least 2 argument(s)', 'fr'];
        yield 'function without script' => ['number > DATE_ADD(othernumber, "PT1H")', 'Function "date_add" is not supported in script conditions'];
        yield 'contains too long' => ['field CONTAINS "'.str_repeat('a', AQLToESQuery::MAX_WILDCARD_VALUE_LENGTH + 1).'"', sprintf('The value of operator "CONTAINS" is limited to %d characters', AQLToESQuery::MAX_WILDCARD_VALUE_LENGTH)];
        yield 'ends with too long' => ['field DOES NOT END WITH "'.str_repeat('a', AQLToESQuery::MAX_WILDCARD_VALUE_LENGTH + 1).'"', sprintf('The value of operator "NOT_ENDS_WITH" is limited to %d characters', AQLToESQuery::MAX_WILDCARD_VALUE_LENGTH)];
    }

    public function testWildcardValueUpToTheLimitIsAccepted(): void
    {
        $value = str_repeat('é', AQLToESQuery::MAX_WILDCARD_VALUE_LENGTH);

        $this->assertSame(
            ['wildcard' => ['attrs.fr.field_text_s.raw' => ['value' => '*'.$value.'*', 'boost' => 1.0, 'case_insensitive' => true]]],
            $this->convert(sprintf('field CONTAINS "%s"', $value), 'fr'),
        );
    }

    /**
     * The values of a script are parameters: the conditions of the same shape share their source,
     * hence a single compilation by Elasticsearch.
     */
    public function testScriptValuesArePassedAsParameters(): void
    {
        $first = $this->convert('number > othernumber * 2 + 1');
        $second = $this->convert('number > othernumber * 7 + 30');

        $source = '(!doc["attrs._.number_number_s"].empty ? doc["attrs._.number_number_s"].value : null) > (((!doc["attrs._.othernumber_number_s"].empty ? doc["attrs._.othernumber_number_s"].value : null) * params.v0) + params.v1)';
        $this->assertSame(['script' => ['script' => ['source' => $source, 'params' => ['v0' => 2, 'v1' => 1]]]], $first);
        $this->assertSame(['script' => ['script' => ['source' => $source, 'params' => ['v0' => 7, 'v1' => 30]]]], $second);
    }

    public function testScriptStringLiteralIsAParameter(): void
    {
        $this->assertSame(['script' => ['script' => [
            'source' => '(!doc["attrs.fr.foo_text_s"].empty ? doc["attrs.fr.foo_text_s"].value : null) = (!doc["attrs.fr.field_text_s"].empty ? doc["attrs.fr.field_text_s"].value : null).Concat(params.v0)',
            'params' => ['v0' => "x'); doc.clear(); ('"],
        ]]], $this->convert('foo = CONCAT(field, "x\'); doc.clear(); (\'")', 'fr'));
    }

    /**
     * Without a search locale, a script reads a translatable field in each locale of its workspaces
     * and untranslated, the fields of an expression in the same locale.
     */
    public function testScriptReadsTranslatableFieldsInEachLocale(): void
    {
        $script = static fn (string $l): array => ['script' => ['script' => [
            'source' => sprintf('(!doc["attrs.%1$s.foo_text_s"].empty ? doc["attrs.%1$s.foo_text_s"].value : null) = (!doc["attrs.%1$s.field_text_s"].empty ? doc["attrs.%1$s.field_text_s"].value : null).Substring(params.v0)', $l),
            'params' => ['v0' => 1],
        ]]];

        $this->assertSame(['bool' => [
            'minimum_should_match' => 1,
            'should' => [$script('it'), $script('de'), $script('_')],
        ]], $this->convert('foo = SUBSTRING(field, 1)'));
    }

    private function convert(string $expression, ?string $locale = null, array $options = []): array
    {
        $parser = new AQLParser();
        $result = $parser->parse($expression);
        $this->assertIsArray($result, 'Parse error');
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturnCallback(fn (string $class, mixed $id): ?Collection => $this->createCollectionStub($class, $id));
        $translator = $this->createStub(TranslatorInterface::class);
        $security = $this->createStub(Security::class);
        $collectionSecurity = $this->createStub(Security::class);
        $collectionSecurity->method('isGranted')->willReturnCallback(
            static fn (mixed $attribute, mixed $subject = null): bool => $subject instanceof Collection && self::COLL_SECRET !== $subject->getId(),
        );

        $functionRegistry = new AQLFunctionRegistry();
        $functionRegistry->register(new MockNowFunction());

        $attributeTypeRegistry = AttributeTypeRegistryTestFactory::create();

        $container = new class([WorkspaceBuiltInAttribute::getKey() => fn () => new WorkspaceBuiltInAttribute($em), AssetStatusBuiltInAttribute::getKey() => fn () => new AssetStatusBuiltInAttribute($translator), DeletedBuiltInAttribute::getKey() => fn () => new DeletedBuiltInAttribute(), CreatedAtBuiltInAttribute::getKey() => fn () => new CreatedAtBuiltInAttribute(), CollectionBuiltInAttribute::getKey() => fn () => new CollectionBuiltInAttribute($em, $collectionSecurity), DirectCollectionBuiltInAttribute::getKey() => fn () => new DirectCollectionBuiltInAttribute($em, $collectionSecurity), FileFamilyBuiltInAttribute::getKey() => fn () => new FileFamilyBuiltInAttribute($translator), SimilarBuiltInAttribute::getKey() => fn () => $this->createSimilarBuiltInAttribute(), OwnerBuiltInAttribute::getKey() => fn () => $this->createDisabledOwnerBuiltInAttribute($security)]) implements ServiceProviderInterface {
            use ServiceLocatorTrait;
        };
        $builtInAttributeRegistry = new BuiltInAttributeRegistry($container);

        $esQueryConverter = new AQLToESQuery(
            $builtInAttributeRegistry, $functionRegistry, $attributeTypeRegistry, new DateNormalizer());

        $fieldClusters = [
            [
                'fields' => [
                    'attrs.{l}.foo_text_s' => [
                        'type' => $attributeTypeRegistry->getStrictType(TextAttributeType::NAME),
                    ],
                    'attrs.{l}.field_text_s' => [
                        'type' => $attributeTypeRegistry->getStrictType(TextAttributeType::NAME),
                    ],
                    'attrs._.number_number_s' => [
                        'type' => $attributeTypeRegistry->getStrictType(NumberAttributeType::NAME),
                    ],
                    'attrs._.othernumber_number_s' => [
                        'type' => $attributeTypeRegistry->getStrictType(NumberAttributeType::NAME),
                    ],
                    'attrs._.n0_number_s' => [
                        'type' => $attributeTypeRegistry->getStrictType(NumberAttributeType::NAME),
                    ],
                    'attrs._.n1_number_s' => [
                        'type' => $attributeTypeRegistry->getStrictType(NumberAttributeType::NAME),
                    ],
                    'attrs._.n2_number_s' => [
                        'type' => $attributeTypeRegistry->getStrictType(NumberAttributeType::NAME),
                    ],
                    'attrs._.n3_number_s' => [
                        'type' => $attributeTypeRegistry->getStrictType(NumberAttributeType::NAME),
                    ],
                    'attrs.{l}.hybrid_text_s' => [
                        'type' => $attributeTypeRegistry->getStrictType(TextAttributeType::NAME),
                    ],
                    'attrs._.location_geo-point_s' => [
                        'type' => $attributeTypeRegistry->getStrictType(GeoPointAttributeType::NAME),
                    ],
                    'attrs._.date_date_s' => [
                        'type' => $attributeTypeRegistry->getStrictType(DateAttributeType::NAME),
                    ],
                    'attrs._.bool_boolean_s' => [
                        'type' => $attributeTypeRegistry->getStrictType(BooleanAttributeType::NAME),
                    ],
                ],
                'w' => [],
                'locales' => ['it', 'de'],
            ],
            [
                'fields' => [
                    'attrs.{l}.www_text_s' => [
                        'type' => $attributeTypeRegistry->getStrictType(TextAttributeType::NAME),
                    ],
                    'attrs.{l}.hybrid_number_s' => [
                        'type' => $attributeTypeRegistry->getStrictType(NumberAttributeType::NAME),
                    ],
                ],
                'w' => ['4242'],
                'locales' => ['fr'],
            ],
        ];

        return $esQueryConverter->createQuery($fieldClusters, $result['data'], [
            'locale' => $locale,
            ...$options,
        ])->toArray();
    }

    /**
     * Requires a role that an anonymous user lacks.
     */
    private function createDisabledOwnerBuiltInAttribute(Security $security): OwnerBuiltInAttribute
    {
        $attribute = new OwnerBuiltInAttribute($this->createStub(UserRepositoryInterface::class), 'admin');
        $attribute->setSecurity($security);

        return $attribute;
    }

    private function createSimilarBuiltInAttribute(): SimilarBuiltInAttribute
    {
        $repository = $this->createMock(AssetRepository::class);
        $repository->method('find')->willReturn(new Asset());

        $similarAssetSearch = $this->createMock(SimilarAssetSearch::class);
        $similarAssetSearch->method('createKnnQuery')->willReturn(new Knn('embedding', [0.1, 0.2], 500));

        $security = $this->createMock(Security::class);
        $security->method('isGranted')->willReturn(true);

        $attribute = new SimilarBuiltInAttribute($repository, $similarAssetSearch);
        $attribute->setSecurity($security);

        return $attribute;
    }

    private function createCollectionStub(string $class, mixed $id): ?Collection
    {
        if (Collection::class !== $class || !isset(self::COLLECTION_PATHS[$id])) {
            return null;
        }

        $collection = $this->createMock(Collection::class);
        $collection->method('getId')->willReturn($id);
        $collection->method('getAbsolutePath')->willReturn(self::COLLECTION_PATHS[$id]);

        return $collection;
    }

    public static function getCases(): array
    {
        return [
            // @collection is recursive: the "collectionPaths" field is analyzed
            // with a path_hierarchy tokenizer, so descendants match too.
            ['@collection = "'.self::COLL_A.'"', [
                'term' => ['collectionPaths' => '/'.self::COLL_A],
            ]],
            ['@collection IN ("'.self::COLL_A.'", "'.self::COLL_B.'")', [
                'terms' => ['collectionPaths' => [
                    '/'.self::COLL_A,
                    '/'.self::COLL_A.'/'.self::COLL_B,
                ]],
            ]],
            // @directCollection targets the raw keyword sub field: only assets
            // directly attached to the given collections match.
            ['@directCollection = "'.self::COLL_A.'"', [
                'term' => ['collectionPaths.raw' => '/'.self::COLL_A],
            ]],
            ['@directCollection IN ("'.self::COLL_A.'", "'.self::COLL_B.'")', [
                'terms' => ['collectionPaths.raw' => [
                    '/'.self::COLL_A,
                    '/'.self::COLL_A.'/'.self::COLL_B,
                ]],
            ]],
            ['@directCollection NOT IN ("'.self::COLL_B.'")', [
                'bool' => [
                    'must_not' => [
                        ['terms' => ['collectionPaths.raw' => [
                            '/'.self::COLL_A.'/'.self::COLL_B,
                        ]]],
                    ],
                ],
            ]],
            ['@directCollection != "'.self::COLL_A.'"', [
                'bool' => [
                    'must_not' => [
                        ['term' => ['collectionPaths.raw' => '/'.self::COLL_A]],
                    ],
                ],
            ]],
            ['@directCollection EXISTS', [
                'exists' => ['field' => 'collectionPaths.raw'],
            ]],
            ['@directCollection = "not-an-uuid"', 'Invalid collection ID'],
            ['@directCollection = "2b2c8b1e-0000-4000-8000-0000000000ff"', 'Collection not found'],
            ['@directCollection CONTAINS "foo"', 'Operator "CONTAINS" not supported for field type "collection_path"'],
            ['@similar = "fa2a50ea-ec37-45a1-8fbc-227344e25e9d"', [
                'knn' => [
                    'field' => 'embedding',
                    'query_vector' => [0.1, 0.2],
                    'num_candidates' => 500,
                ],
            ]],
            ['date < "YYYY-88-88"', 'Invalid date value "YYYY-88-88"'],
            ['date < "9999-88-88"', [
                'range' => [
                    'attrs._.date_date_s' => ['lt' => '10006-06-27'],
                ],
            ]],
            ['date < ""', 'Invalid date value ""'],
            ['date < "2015"', [
                'range' => [
                    'attrs._.date_date_s' => ['lt' => 2015],
                ],
            ]],
            ['date=  "2015"', [
                'prefix' => [
                    'attrs._.date_date_s.raw' => [
                        'value' => '2015',
                        'boost' => 1.0,
                    ],
                ],
            ]],
            ['date=  "2015-05"', [
                'prefix' => [
                    'attrs._.date_date_s.raw' => [
                        'value' => '2015-05',
                        'boost' => 1.0,
                    ],
                ],
            ]],
            ['date="2015-05-22"', [
                'prefix' => [
                    'attrs._.date_date_s.raw' => [
                        'value' => '2015-05-22',
                        'boost' => 1.0,
                    ],
                ],
            ]],
            ['date="2015-05-22 15"', [
                'prefix' => [
                    'attrs._.date_date_s.raw' => [
                        'value' => '2015-05-22T15',
                        'boost' => 1.0,
                    ],
                ],
            ]],
            ['date="2015-05-22T15"', [
                'prefix' => [
                    'attrs._.date_date_s.raw' => [
                        'value' => '2015-05-22T15',
                        'boost' => 1.0,
                    ],
                ],
            ]],
            ['date="2015-05-22T10:05"', [
                'prefix' => [
                    'attrs._.date_date_s.raw' => [
                        'value' => '2015-05-22T10:05',
                        'boost' => 1.0,
                    ],
                ],
            ]],
            ['date="2015-05-22T10:05:42"', [
                'prefix' => [
                    'attrs._.date_date_s.raw' => [
                        'value' => '2015-05-22T10:05:42',
                        'boost' => 1.0,
                    ],
                ],
            ]],
            ['date="2015-05-22 10:05:42"', [
                'prefix' => [
                    'attrs._.date_date_s.raw' => [
                        'value' => '2015-05-22T10:05:42',
                        'boost' => 1.0,
                    ],
                ],
            ]],
            ['date !=  "2015"', [
                'bool' => [
                    'must_not' => [
                        [
                            'prefix' => [
                                'attrs._.date_date_s.raw' => [
                                    'value' => '2015',
                                    'boost' => 1.0,
                                ],
                            ],
                        ],
                    ],
                ],
            ]],
            ['date !=  "2015-05"', [
                'bool' => [
                    'must_not' => [
                        [
                            'prefix' => [
                                'attrs._.date_date_s.raw' => [
                                    'value' => '2015-05',
                                    'boost' => 1.0,
                                ],
                            ],
                        ],
                    ],
                ],
            ]],
            ['date !=  "2015-05-22"', [
                'bool' => [
                    'must_not' => [
                        [
                            'prefix' => [
                                'attrs._.date_date_s.raw' => [
                                    'value' => '2015-05-22',
                                    'boost' => 1.0,
                                ],
                            ],
                        ],
                    ],
                ],
            ]],
            ['foo="bar"', [
                'term' => [
                    'attrs.fr.foo_text_s.raw' => 'bar',
                ],
            ], 'fr'],
            ['@workspace="42"', [
                'term' => ['workspaceId' => '42'],
            ]],
            ['@family="image"', [
                'term' => ['fileFamily' => 'image'],
            ]],
            ['@family IN ("audio", "video")', [
                'terms' => ['fileFamily' => ['audio', 'video']],
            ]],
            ['@workspace=SUBSTRING("42aa", 0, 2)', [
                'term' => ['workspaceId' => '42'],
            ]],
            ['@workspace= NULL', [
                'term' => ['workspaceId' => null],
            ]],
            ['@workspace= null', [
                'term' => ['workspaceId' => null],
            ]],
            ['@deleted = true', [
                'bool' => [
                    'must' => [
                        [
                            'bool' => [
                                'minimum_should_match' => 1,
                                'should' => [
                                    ['term' => ['ownerId' => AbstractSearch::NO_AUTH]],
                                ],
                            ],
                        ],
                        ['bool' => [
                            'should' => [
                                ['term' => ['deleted' => true]],
                                ['term' => ['collectionDeleted' => true]],
                            ],
                        ]],
                    ],
                ],
            ]],
            ['@deleted = false', [
                'bool' => [
                    'must' => [
                        ['term' => ['deleted' => false]],
                        ['term' => ['collectionDeleted' => false]],
                    ],
                ],
            ]],
            ['@assetStatus IN (0, 1)', [
                'bool' => [
                    'minimum_should_match' => 1,
                    'should' => [
                        [
                            'bool' => [
                                'must' => [
                                    ['term' => ['status' => 0]],
                                ],
                            ],
                        ], [
                            'bool' => [
                                'must' => [
                                    ['term' => ['status' => 1]],
                                    ['term' => ['ownerId' => AbstractSearch::NO_AUTH]],
                                ],
                            ],
                        ],
                    ],
                ],
            ]],
            ['bool = null', [
                'bool' => [
                    'must_not' => [
                        [
                            'exists' => ['field' => 'attrs._.bool_boolean_s'],
                        ],
                    ],
                ],
            ]],
            ['bool = true', [
                'term' => ['attrs._.bool_boolean_s' => true],
            ]],
            ['bool = false', [
                'term' => ['attrs._.bool_boolean_s' => false],
            ]],
            ['bool = "maybe"', 'Invalid boolean value "maybe"'],
            ['@createdAt<="2025-01-16"', [
                'range' => ['createdAt' => [
                    'lte' => '2025-01-16',
                ]],
            ]],
            ['@createdAt<= (42) - 5 - 5', [
                'range' => ['createdAt' => [
                    'lte' => (42 - 5 - 5) * 1000,
                ]],
            ]],
            ['field IN (true, false)', [
                'bool' => [
                    'should' => [
                        ['terms' => ['attrs.it.field_text_s.raw' => [true, false]]],
                        ['terms' => ['attrs.de.field_text_s.raw' => [true, false]]],
                        ['terms' => ['attrs._.field_text_s.raw' => [true, false]]],
                    ],
                ],
            ]],
            ['field IN (true, null)', [
                'bool' => [
                    'should' => [
                        ['terms' => ['attrs.it.field_text_s.raw' => [true, null]]],
                        ['terms' => ['attrs.de.field_text_s.raw' => [true, null]]],
                        ['terms' => ['attrs._.field_text_s.raw' => [true, null]]],
                    ],
                ],
            ]],
            ['field IN (true, n1)', 'Unsupported operator "IN" in script conditions'],
            ['field CONTAINS "big world"', [
                'bool' => [
                    'should' => [
                        ['wildcard' => ['attrs.it.field_text_s.raw' => ['value' => '*big world*', 'boost' => 1.0, 'case_insensitive' => true]]],
                        ['wildcard' => ['attrs.de.field_text_s.raw' => ['value' => '*big world*', 'boost' => 1.0, 'case_insensitive' => true]]],
                        ['wildcard' => ['attrs._.field_text_s.raw' => ['value' => '*big world*', 'boost' => 1.0, 'case_insensitive' => true]]],
                    ],
                ],
            ]],
            ['field CONTAINS "a*b?c (d): e/f"', [
                'wildcard' => ['attrs.fr.field_text_s.raw' => ['value' => '*a\\*b\\?c (d): e/f*', 'boost' => 1.0, 'case_insensitive' => true]],
            ], 'fr'],
            ['field DOES NOT CONTAIN "big world"', [
                'bool' => [
                    'must_not' => [
                        ['wildcard' => ['attrs.fr.field_text_s.raw' => ['value' => '*big world*', 'boost' => 1.0, 'case_insensitive' => true]]],
                    ],
                ],
            ], 'fr'],
            ['field CONTAINS "Big World" CASE SENSITIVE', [
                'wildcard' => ['attrs.fr.field_text_s.raw' => ['value' => '*Big World*', 'boost' => 1.0]],
            ], 'fr'],
            ['field DOES NOT START WITH "Big" CASE SENSITIVE', [
                'bool' => [
                    'must_not' => [
                        ['prefix' => ['attrs.fr.field_text_s.raw' => ['value' => 'Big', 'boost' => 1.0]]],
                    ],
                ],
            ], 'fr'],
            ['field MATCHES "Big" CASE SENSITIVE', 'CASE SENSITIVE is not supported with operator "MATCHES"'],
            ['field MATCHES "big world"', [
                'match' => ['attrs.fr.field_text_s' => ['query' => 'big world', 'operator' => 'and']],
            ], 'fr'],
            ['field DOES NOT MATCH "big world"', [
                'bool' => [
                    'must_not' => [
                        ['match' => ['attrs.fr.field_text_s' => ['query' => 'big world', 'operator' => 'and']]],
                    ],
                ],
            ], 'fr'],
            ['field ENDS WITH "world"', [
                'bool' => [
                    'should' => [
                        ['wildcard' => ['attrs.it.field_text_s.raw' => ['value' => '*world', 'boost' => 1.0, 'case_insensitive' => true]]],
                        ['wildcard' => ['attrs.de.field_text_s.raw' => ['value' => '*world', 'boost' => 1.0, 'case_insensitive' => true]]],
                        ['wildcard' => ['attrs._.field_text_s.raw' => ['value' => '*world', 'boost' => 1.0, 'case_insensitive' => true]]],
                    ],
                ],
            ]],
            ['field ENDS WITH "a*b?"', [
                'wildcard' => ['attrs.fr.field_text_s.raw' => ['value' => '*a\\*b\\?', 'boost' => 1.0, 'case_insensitive' => true]],
            ], 'fr'],
            ['field DOES NOT END WITH "World" CASE SENSITIVE', [
                'bool' => [
                    'must_not' => [
                        ['wildcard' => ['attrs.fr.field_text_s.raw' => ['value' => '*World', 'boost' => 1.0]]],
                    ],
                ],
            ], 'fr'],
            ['field HAS ALL OF ("a", "b")', [
                'bool' => [
                    'must' => [
                        ['term' => ['attrs.fr.field_text_s.raw' => 'a']],
                        ['term' => ['attrs.fr.field_text_s.raw' => 'b']],
                    ],
                ],
            ], 'fr'],
            ['field IS ANY OF ("a", "b")', [
                'terms' => ['attrs.fr.field_text_s.raw' => ['a', 'b']],
            ], 'fr'],
            ['field IS NONE OF ("a")', [
                'bool' => [
                    'must_not' => [
                        ['terms' => ['attrs.fr.field_text_s.raw' => ['a']]],
                    ],
                ],
            ], 'fr'],
            ['field IS "a"', [
                'term' => ['attrs.fr.field_text_s.raw' => 'a'],
            ], 'fr'],
            ['field IS NOT "a"', [
                'bool' => [
                    'must_not' => [
                        ['term' => ['attrs.fr.field_text_s.raw' => 'a']],
                    ],
                ],
            ], 'fr'],
            ['field IS EMPTY', [
                'bool' => [
                    'must_not' => [
                        ['exists' => ['field' => 'attrs.fr.field_text_s']],
                    ],
                ],
            ], 'fr'],
            ['field IS NOT EMPTY', [
                'exists' => ['field' => 'attrs.fr.field_text_s'],
            ], 'fr'],
            ['field STARTS WITH "big world"', [
                'bool' => [
                    'should' => [
                        ['prefix' => ['attrs.it.field_text_s.raw' => ['value' => 'big world', 'boost' => 1.0, 'case_insensitive' => true]]],
                        ['prefix' => ['attrs.de.field_text_s.raw' => ['value' => 'big world', 'boost' => 1.0, 'case_insensitive' => true]]],
                        ['prefix' => ['attrs._.field_text_s.raw' => ['value' => 'big world', 'boost' => 1.0, 'case_insensitive' => true]]],
                    ],
                ],
            ]],
            ['number > othernumber', [
                'script' => [
                    'script' => [
                        'source' => '(!doc["attrs._.number_number_s"].empty ? doc["attrs._.number_number_s"].value : null) > (!doc["attrs._.othernumber_number_s"].empty ? doc["attrs._.othernumber_number_s"].value : null)',
                    ],
                ],
            ]],
            ['number > othernumber * 2', [
                'script' => [
                    'script' => [
                        'source' => '(!doc["attrs._.number_number_s"].empty ? doc["attrs._.number_number_s"].value : null) > ((!doc["attrs._.othernumber_number_s"].empty ? doc["attrs._.othernumber_number_s"].value : null) * params.v0)',
                        'params' => ['v0' => 2],
                    ],
                ],
            ]],
            ['number > othernumber * (2 + 1)', [
                'script' => [
                    'script' => [
                        'source' => '(!doc["attrs._.number_number_s"].empty ? doc["attrs._.number_number_s"].value : null) > ((!doc["attrs._.othernumber_number_s"].empty ? doc["attrs._.othernumber_number_s"].value : null) * params.v0)',
                        'params' => ['v0' => 3],
                    ],
                ],
            ]],
            ['n0 > n1 * (n2 + n3)', [
                'script' => [
                    'script' => [
                        'source' => '(!doc["attrs._.n0_number_s"].empty ? doc["attrs._.n0_number_s"].value : null) > ((!doc["attrs._.n1_number_s"].empty ? doc["attrs._.n1_number_s"].value : null) * ((!doc["attrs._.n2_number_s"].empty ? doc["attrs._.n2_number_s"].value : null) + (!doc["attrs._.n3_number_s"].empty ? doc["attrs._.n3_number_s"].value : null)))',
                    ],
                ],
            ]],
            ['n0 > n1 * (n2 + n3)', [
                'script' => [
                    'script' => [
                        'source' => '(!doc["attrs._.n0_number_s"].empty ? doc["attrs._.n0_number_s"].value : null) > ((!doc["attrs._.n1_number_s"].empty ? doc["attrs._.n1_number_s"].value : null) * ((!doc["attrs._.n2_number_s"].empty ? doc["attrs._.n2_number_s"].value : null) + (!doc["attrs._.n3_number_s"].empty ? doc["attrs._.n3_number_s"].value : null)))',
                    ],
                ],
            ]],
            ['@createdAt > now() * 8 - 3', [
                'range' => ['createdAt' => [
                    'gt' => (MockNowFunction::VALUE * 8 - 3) * 1000,
                ]],
            ]],
            ['foo MATCHES SUBSTRING("hello", 1, 2)', [
                'multi_match' => [
                    'query' => 'el',
                    'fields' => ['attrs.*.foo_text_s'],
                    'operator' => 'and',
                ],
            ]],
            ['@createdAt > DATE_ADD(NOW(), "PT1H")', [
                'range' => ['createdAt' => [
                    'gt' => (MockNowFunction::VALUE + 3600) * 1000,
                ]],
            ]],
            ['@createdAt > DATE_SUB(NOW(), "PT1M")', [
                'range' => ['createdAt' => [
                    'gt' => (MockNowFunction::VALUE - 60) * 1000,
                ]],
            ]],
            ['foo = SUBSTRING(foo, 1, 2)', [
                'script' => [
                    'script' => ['source' => '(!doc["attrs.fr.foo_text_s"].empty ? doc["attrs.fr.foo_text_s"].value : null) = (!doc["attrs.fr.foo_text_s"].empty ? doc["attrs.fr.foo_text_s"].value : null).Substring(params.v0, params.v1)', 'params' => ['v0' => 1, 'v1' => 2]],
                ],
            ], 'fr'],
            ['www = SUBSTRING(foo, 1, 2)', [
                'bool' => [
                    'must' => [
                        [
                            'script' => [
                                'script' => ['source' => '(!doc["attrs.fr.www_text_s"].empty ? doc["attrs.fr.www_text_s"].value : null) = (!doc["attrs.fr.foo_text_s"].empty ? doc["attrs.fr.foo_text_s"].value : null).Substring(params.v0, params.v1)', 'params' => ['v0' => 1, 'v1' => 2]],
                            ],
                        ],
                        [
                            'terms' => [
                                'workspaceId' => ['4242'],
                            ],
                        ],
                    ],
                ],
            ], 'fr'],
            ['www = SUBSTRING(hybrid, 1, 2)', [
                'bool' => [
                    'minimum_should_match' => 1,
                    'should' => [
                        [
                            'bool' => [
                                'must' => [
                                    [
                                        'script' => [
                                            'script' => ['source' => '(!doc["attrs.fr.www_text_s"].empty ? doc["attrs.fr.www_text_s"].value : null) = (!doc["attrs.fr.hybrid_text_s"].empty ? doc["attrs.fr.hybrid_text_s"].value : null).Substring(params.v0, params.v1)', 'params' => ['v0' => 1, 'v1' => 2]],
                                        ],
                                    ],
                                    [
                                        'terms' => [
                                            'workspaceId' => ['4242'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        [
                            'bool' => [
                                'must' => [
                                    [
                                        'script' => [
                                            'script' => ['source' => '(!doc["attrs.fr.www_text_s"].empty ? doc["attrs.fr.www_text_s"].value : null) = (!doc["attrs.fr.hybrid_number_s"].empty ? doc["attrs.fr.hybrid_number_s"].value : null).Substring(params.v0, params.v1)', 'params' => ['v0' => 1, 'v1' => 2]],
                                        ],
                                    ],
                                    [
                                        'terms' => [
                                            'workspaceId' => ['4242'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ], 'fr'],
            ['foo = SUBSTRING(hybrid, 1, 2)', [
                'bool' => [
                    'minimum_should_match' => 1,
                    'should' => [
                        [
                            'script' => [
                                'script' => ['source' => '(!doc["attrs.fr.foo_text_s"].empty ? doc["attrs.fr.foo_text_s"].value : null) = (!doc["attrs.fr.hybrid_text_s"].empty ? doc["attrs.fr.hybrid_text_s"].value : null).Substring(params.v0, params.v1)', 'params' => ['v0' => 1, 'v1' => 2]],
                            ],
                        ],
                        [
                            'bool' => [
                                'must' => [
                                    [
                                        'script' => [
                                            'script' => ['source' => '(!doc["attrs.fr.foo_text_s"].empty ? doc["attrs.fr.foo_text_s"].value : null) = (!doc["attrs.fr.hybrid_number_s"].empty ? doc["attrs.fr.hybrid_number_s"].value : null).Substring(params.v0, params.v1)', 'params' => ['v0' => 1, 'v1' => 2]],
                                        ],
                                    ],
                                    [
                                        'terms' => [
                                            'workspaceId' => ['4242'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ], 'fr'],
            ['hybrid = SUBSTRING(foo, 1, 2)', [
                'bool' => [
                    'minimum_should_match' => 1,
                    'should' => [
                        [
                            'script' => [
                                'script' => ['source' => '(!doc["attrs.fr.hybrid_text_s"].empty ? doc["attrs.fr.hybrid_text_s"].value : null) = (!doc["attrs.fr.foo_text_s"].empty ? doc["attrs.fr.foo_text_s"].value : null).Substring(params.v0, params.v1)', 'params' => ['v0' => 1, 'v1' => 2]],
                            ],
                        ],
                        [
                            'bool' => [
                                'must' => [
                                    [
                                        'script' => [
                                            'script' => ['source' => '(!doc["attrs.fr.hybrid_number_s"].empty ? doc["attrs.fr.hybrid_number_s"].value : null) = (!doc["attrs.fr.foo_text_s"].empty ? doc["attrs.fr.foo_text_s"].value : null).Substring(params.v0, params.v1)', 'params' => ['v0' => 1, 'v1' => 2]],
                                        ],
                                    ],
                                    [
                                        'terms' => [
                                            'workspaceId' => ['4242'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ], 'fr'],
            [
                'location WITHIN CIRCLE (48.8, 2.32, "10km")',
                [
                    'geo_distance' => [
                        'distance' => '10km',
                        'attrs._.location_geo-point_s' => [
                            'lat' => 48.8,
                            'lon' => 2.32,
                        ],
                    ],
                ],
            ],
            [
                'location WITHIN RECTANGLE (1.1, 1.2, 2.1, 2.2)',
                [
                    'geo_bounding_box' => [
                        'attrs._.location_geo-point_s' => [
                            'top_left' => ['lat' => 1.1, 'lon' => 1.2],
                            'bottom_right' => ['lat' => 2.1, 'lon' => 2.2],
                        ],
                    ],
                ],
            ],
            [
                'date CONTAINS "2023-10-01"',
                'Operator "CONTAINS" not supported for field type "date"',
            ],
            [
                'date START WITH "2023-10-01"',
                'Operator "STARTS_WITH" not supported for field type "date"',
            ],
            [
                'date ENDS WITH "01"',
                'Operator "ENDS_WITH" not supported for field type "date"',
            ],
            [
                'number MATCHES "1"',
                'Operator "MATCHES" not supported for field type "number"',
            ],
        ];
    }
}
