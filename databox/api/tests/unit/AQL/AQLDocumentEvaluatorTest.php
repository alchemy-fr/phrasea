<?php

declare(strict_types=1);

namespace App\Tests\Unit\AQL;

use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use Alchemy\AuthBundle\Repository\UserRepositoryInterface;
use App\Attribute\AttributeTypeRegistry;
use App\Attribute\Type\AssetStatusAttributeType;
use App\Attribute\Type\BooleanAttributeType;
use App\Attribute\Type\CollectionPathAttributeType;
use App\Attribute\Type\DateAttributeType;
use App\Attribute\Type\DateTimeAttributeType;
use App\Attribute\Type\GeoPointAttributeType;
use App\Attribute\Type\IdAttributeType;
use App\Attribute\Type\KeywordAttributeType;
use App\Attribute\Type\NumberAttributeType;
use App\Attribute\Type\PrivacyAttributeType;
use App\Attribute\Type\TextAttributeType;
use App\Elasticsearch\AbstractSearch;
use App\Elasticsearch\AQL\AQLConditionResolver;
use App\Elasticsearch\AQL\AQLDocumentEvaluator;
use App\Elasticsearch\AQL\AQLParser;
use App\Elasticsearch\AQL\AQLToESQuery;
use App\Elasticsearch\AQL\DateNormalizer;
use App\Elasticsearch\AQL\Function\AQLFunctionRegistry;
use App\Elasticsearch\AQL\Function\ConcatFunction;
use App\Elasticsearch\AQL\Function\SubstringFunction;
use App\Elasticsearch\AQL\InvalidAQLException;
use App\Elasticsearch\AQL\UnsupportedEvaluationException;
use App\Elasticsearch\BuiltInAttribute\AssetStatusBuiltInAttribute;
use App\Elasticsearch\BuiltInAttribute\BuiltInAttributeRegistry;
use App\Elasticsearch\BuiltInAttribute\CollectionBuiltInAttribute;
use App\Elasticsearch\BuiltInAttribute\CreatedAtBuiltInAttribute;
use App\Elasticsearch\BuiltInAttribute\DeletedBuiltInAttribute;
use App\Elasticsearch\BuiltInAttribute\DirectCollectionBuiltInAttribute;
use App\Elasticsearch\BuiltInAttribute\FileNameBuiltInAttribute;
use App\Elasticsearch\BuiltInAttribute\IdBuiltInAttribute;
use App\Elasticsearch\BuiltInAttribute\OwnerBuiltInAttribute;
use App\Elasticsearch\BuiltInAttribute\PrivacyBuiltInAttribute;
use App\Elasticsearch\BuiltInAttribute\SimilarBuiltInAttribute;
use App\Elasticsearch\BuiltInAttribute\WorkspaceBuiltInAttribute;
use App\Elasticsearch\Query\Knn;
use App\Elasticsearch\SimilarAssetSearch;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Repository\Core\AssetRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Service\ServiceLocatorTrait;
use Symfony\Contracts\Service\ServiceProviderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AQLDocumentEvaluatorTest extends TestCase
{
    private const string ASSET_ID = 'a5e7a5e7-0000-4000-8000-000000000001';
    private const string COLL_A = '2b2c8b1e-0000-4000-8000-00000000000a';
    private const string COLL_B = '2b2c8b1e-0000-4000-8000-00000000000b';
    private const string COLL_C = '2b2c8b1e-0000-4000-8000-00000000000c';

    private const array COLLECTION_PATHS = [
        self::COLL_A => '/'.self::COLL_A,
        self::COLL_B => '/'.self::COLL_A.'/'.self::COLL_B,
        self::COLL_C => '/'.self::COLL_C,
    ];

    private AttributeTypeRegistry $typeRegistry;

    #[DataProvider('getCases')]
    public function testEvaluate(string $condition, bool $expected, array $options = []): void
    {
        $this->assertSame($expected, $this->evaluate($condition, self::getDocument(), $options), $condition);
    }

    public static function getCases(): iterable
    {
        // keyword
        yield ['category = "press"', true];
        yield ['category = "Press"', false];
        yield ['category != "press"', false];
        yield ['category IS NOT "internal"', true];
        yield ['NOT (category = "press")', false];
        yield ['category IN ("internal", "press")', true];
        yield ['category NOT IN ("press")', false];
        yield ['category CONTAINS "RES"', true];
        yield ['category CONTAINS "RES" CASE SENSITIVE', false];
        yield ['category STARTS WITH "pr"', true];
        yield ['category ENDS WITH "ss"', true];
        yield ['category DOES NOT END WITH "ss"', false];
        yield ['category MATCHES "press"', true];
        yield ['category MATCHES "pres"', false];
        yield ['unknown_keyword IS MISSING', true];

        // multi-valued
        yield ['keywords = "beta"', true];
        yield ['keywords HAS ALL OF ("alpha", "beta")', true];
        yield ['keywords HAS ALL OF ("alpha", "gamma")', false];
        yield ['keywords IS NONE OF ("gamma")', true];

        // logic
        yield ['category = "press" AND count = 42', true];
        yield ['category = "press" AND count = 41', false];
        yield ['category = "internal" OR count = 42', true];
        yield ['NOT (category = "internal" OR count = 41)', true];

        // numbers
        yield ['count > 41', true];
        yield ['count >= 43', false];
        yield ['count > 41.5', true];
        yield ['count < 42.5', true];
        yield ['count BETWEEN 40 AND 42', true];
        yield ['count NOT BETWEEN 40 AND 42', false];
        yield ['count IN (1, 42)', true];
        // A decimal term matches no integer
        yield ['count = 42.5', false];
        yield ['count != 42.5', true];
        // The long field truncates the decimals
        yield ['rate = 4', true];
        yield ['rate > 4', false];

        // booleans
        yield ['published = true', true];
        yield ['published = "0"', false];
        yield ['published = null', false];
        yield ['published != null', true];
        yield ['archived = null', true];

        // dates
        yield ['day = "2015"', true];
        yield ['day = "2015-05"', true];
        yield ['day = "2015-06"', false];
        yield ['day < "2015-05-23"', true];
        yield ['day <= "2015-05-22"', true];
        yield ['day > "2015-05-22"', false];
        yield ['day >= "2015-05-22"', true];
        yield ['moment = "2015-05-22T10:05"', true];
        yield ['moment > "2015-05-22T10:05:41"', true];
        yield ['moment > "2015-05-22T10:05:42"', false];
        yield ['moment BETWEEN "2015-05-22T10:00:00.000+00:00" AND "2015-05-22T11:00:00.000+00:00"', true];
        yield ['@createdAt = "2024-03"', true];
        yield ['@createdAt < "2024-03-15"', false];

        // translated text, any locale: the raw values are exact
        yield ['title = "L\'été à Paris"', true];
        yield ['title = "l\'été à paris"', false];
        yield ['title CONTAINS "SUMMER"', true];
        // Only the ASCII letters are case insensitive
        yield ['title CONTAINS "ÉTÉ"', false];
        yield ['title CONTAINS "ÉTé"', false];
        yield ['title CONTAINS "l\'éTé"', true];
        yield ['title STARTS WITH "summer in"', true];

        // untranslated text, "text" analyzer
        yield ['caption MATCHES "ete paris"', true];
        yield ['caption MATCHES "L\'ÉTÉ"', true];
        yield ['caption MATCHES "été lyon"', false];
        yield ['caption MATCHES "aujourdhui"', true];
        yield ['caption MATCHES "hui"', true];
        yield ['caption MATCHES "?!"', false];
        yield ['caption DOES NOT MATCH "paris"', false];
        // Too long for the raw sub-field
        yield ['long_title CONTAINS "word"', false];
        yield ['long_title IS NOT EMPTY', true];
        yield ['long_title MATCHES "word"', true];

        // geo
        yield ['location WITHIN CIRCLE (48.85, 2.35, "1km")', true];
        yield ['location WITHIN CIRCLE (48.85, 2.35, 100)', false];
        yield ['location WITHIN RECTANGLE (49, 2, 48, 3)', true];
        yield ['location WITHIN RECTANGLE (49, 3, 48, 4)', false];

        // built-ins
        yield ['@workspace = "ws1"', true];
        yield ['@privacy = 4', true];
        yield ['@id = "'.self::ASSET_ID.'"', true];
        yield ['@collection = "'.self::COLL_A.'"', true];
        yield ['@collection = "'.self::COLL_C.'"', false];
        yield ['@directCollection = "'.self::COLL_A.'"', false];
        yield ['@directCollection = "'.self::COLL_B.'"', true];
        yield ['@filename MATCHES "resume"', false];
        yield ['@filename MATCHES "résumé 2024"', true];
        yield ['@filename STARTS WITH "Résumé"', true];
        yield ['@deleted = false', true];
        yield ['@deleted = true', false];
        yield ['@assetStatus = 0', true];
        yield ['@assetStatus = 2', false];

        // cluster restricted to another workspace
        yield ['restricted = "x"', false];

        // scripted criteria
        yield ['count > other', true];
        yield ['count = other + 32', true];
        // A division is decimal
        yield ['other < count / 4', true];
        yield ['count != missing_number', false];
        yield ['count = missing_number', false];
        yield ['category = SUBSTRING(category, 0)', true];
        yield ['category = CONCAT(SUBSTRING(category, 0, 2), "ess")', true];
        yield ['caption = category', false];
        yield ['NOT (caption != category)', false];
    }

    public function testDeletedRequiresPermission(): void
    {
        $document = ['deleted' => true, 'collectionDeleted' => false, 'ownerId' => 'owner1', 'deleteUsers' => ['u2']] + self::getDocument();

        $this->assertFalse($this->evaluate('@deleted = true', $document, ['userId' => 'u1']));
        $this->assertTrue($this->evaluate('@deleted = true', $document, ['userId' => 'u2']));
        $this->assertTrue($this->evaluate('@deleted = true', $document, ['userId' => 'owner1']));
        $this->assertFalse($this->evaluate('@deleted = false', $document, ['userId' => 'owner1']));
        $this->assertTrue($this->evaluate('@deleted = true', ['deleteGroups' => [AccessControlEntryInterface::USER_WILDCARD]] + $document, ['userId' => 'u1']));
        $this->assertFalse($this->evaluate('@deleted = true', ['deleteGroups' => [AccessControlEntryInterface::USER_WILDCARD]] + $document, []));
    }

    public function testQuarantineRequiresPermission(): void
    {
        $document = ['status' => 2, 'ownerId' => 'owner1', 'quarantineGroups' => ['g1']] + self::getDocument();

        $this->assertFalse($this->evaluate('@assetStatus = 2', $document, ['userId' => 'u1']));
        $this->assertTrue($this->evaluate('@assetStatus = 2', $document, ['userId' => 'u1', 'groupIds' => ['g1']]));
        $this->assertTrue($this->evaluate('@assetStatus = 2', ['quarantineUsers' => [AccessControlEntryInterface::USER_WILDCARD]] + $document, ['userId' => 'u1']));
        $this->assertFalse($this->evaluate('@assetStatus = 2', $document, []));
        $this->assertTrue($this->evaluate('@assetStatus = 2', ['ownerId' => AbstractSearch::NO_AUTH] + $document, []));
    }

    public function testScriptedCriteriaReadsFieldsInTheSameLocale(): void
    {
        $document = self::getDocument();
        $document['attrs'][0]['fr']['code_keyword_s'] = 'x';
        $document['attrs'][0]['en']['code_keyword_s'] = 'y';
        $document['attrs'][0]['en']['alias_keyword_s'] = 'x';

        $this->assertFalse($this->evaluate('code = alias', $document));
        $document['attrs'][0]['fr']['alias_keyword_s'] = 'x';
        $this->assertTrue($this->evaluate('code = alias', $document));
    }

    /**
     * Mirror of AQLToESQuery::wrapCluster(): the assets on which an asset policy hides the field.
     */
    public function testHiddenScopesExcludeTheAssets(): void
    {
        $clusters = [[
            'fields' => ['attrs._.secret_keyword_s' => ['type' => $this->createTypeRegistry()->getStrictType(KeywordAttributeType::NAME)]],
            'w' => ['ws1'],
            'locales' => [],
            'x' => [['w' => 'ws1', 'p' => ['/'.self::COLL_A]]],
        ]];
        $document = self::getDocument();
        $document['attrs'][0]['_']['secret_keyword_s'] = 'x';

        $this->assertTrue($this->evaluate('secret = "x"', $document + ['referenceCollectionPath' => '/'.self::COLL_C], [], $clusters));
        $this->assertFalse($this->evaluate('secret = "x"', $document + ['referenceCollectionPath' => '/'.self::COLL_A], [], $clusters));
        $this->assertFalse($this->evaluate('secret = "x"', $document + ['referenceCollectionPath' => self::COLLECTION_PATHS[self::COLL_B]], [], $clusters));
        $this->assertFalse($this->evaluate('secret = secret', $document + ['referenceCollectionPath' => '/'.self::COLL_A], [], $clusters));
    }

    /**
     * A built-in attribute disabled for the user only exists in the conditions of the rules.
     */
    public function testDisabledBuiltInOnlyInTrustedConditions(): void
    {
        $this->assertTrue($this->evaluate('@owner = "owner1"', self::getDocument()));

        $this->expectExceptionMessage('Built-in field "@owner" not found');
        $this->evaluate('@owner = "owner1"', self::getDocument(), [AQLToESQuery::OPT_TRUSTED_CONDITIONS => false]);
    }

    #[DataProvider('getRefusedCases')]
    public function testRefusedConditions(string $condition, string $expectedMessage, string $expectedClass = \Throwable::class): void
    {
        $this->expectException($expectedClass);
        $this->expectExceptionMessage($expectedMessage);
        $this->evaluate($condition, self::getDocument());
    }

    public static function getRefusedCases(): iterable
    {
        yield ['unknown_field = "press"', 'Field "unknown_field" not found'];
        yield ['@similar = "'.self::ASSET_ID.'"', 'cannot be evaluated outside of Elasticsearch', UnsupportedEvaluationException::class];
        yield ['category MATCHES "a" CASE SENSITIVE', 'CASE SENSITIVE is not supported with operator "MATCHES"'];
        yield ['count CONTAINS "4"', 'Operator "CONTAINS" not supported for field type "number"'];
        yield ['category = null', 'A term query expects a single non-null value'];
        yield ['count = "abc"', 'Invalid number "abc"'];
        yield ['moment BETWEEN "2015-01-01" AND "2016-01-01"', 'strict_date_time'];
        yield ['other < count / 0', 'Division by zero', InvalidAQLException::class];
        yield ['category CONTAINS "'.str_repeat('a', AQLToESQuery::MAX_WILDCARD_VALUE_LENGTH + 1).'"', 'is limited to', InvalidAQLException::class];
        // The language analyzers are not reproduced
        yield ['title MATCHES "summer"', 'analyzer is not reproduced', UnsupportedEvaluationException::class];
        // Painless refuses to order strings
        yield ['category > keywords', 'Cannot compare', UnsupportedEvaluationException::class];
        yield ['@privacy = 300', 'out of range', UnsupportedEvaluationException::class];
        // Integers from 2^53 on do not all have a float
        yield ['count = 9007199254740993', 'exact comparison', UnsupportedEvaluationException::class];
        yield ['count > 9007199254740992', 'exact comparison', UnsupportedEvaluationException::class];
        yield ['count BETWEEN 0 AND 9007199254740993', 'exact comparison', UnsupportedEvaluationException::class];
    }

    /**
     * Elasticsearch compares the long fields exactly, beyond the integers a float holds.
     */
    public function testLongValuesBeyondFloatPrecision(): void
    {
        $document = self::getDocument();
        $document['attrs'][0]['_']['count_number_s'] = 9007199254740993;

        $this->assertFalse($this->evaluate('count = 9007199254740991', $document));
        $this->assertTrue($this->evaluate('count > 9007199254740991', $document));
        $this->assertFalse($this->evaluate('count < 9007199254740991', $document));
        $this->assertTrue($this->evaluate('count > other', $document));

        $document['attrs'][0]['_']['count_number_s'] = '9007199254740993';
        $this->expectException(UnsupportedEvaluationException::class);
        $this->evaluate('count = 42', $document);
    }

    public function testUnsupportedCharactersAreRefused(): void
    {
        $document = self::getDocument();
        $document['attrs'][0]['_']['caption_text_s'] = 'Ελλάδα';

        $this->expectException(UnsupportedEvaluationException::class);
        $this->evaluate('caption MATCHES "x"', $document);
    }

    public function testCaseInsensitiveMatchOfAmbiguousCharactersIsRefused(): void
    {
        $document = self::getDocument();
        $document['attrs'][0]['_']['category_keyword_s'] = "\u{212A}ilo";

        $this->expectException(UnsupportedEvaluationException::class);
        $this->evaluate('category STARTS WITH "k"', $document);
    }

    /**
     * An invalid condition throws whatever the document, as the query builder refuses it.
     */
    public function testInvalidConditionThrowsOnAnyDocument(): void
    {
        $this->expectExceptionMessage('A term query expects a single non-null value');
        $this->evaluate('restricted = null', self::getDocument());
    }

    /**
     * The conditions are trusted, as those of the attribute filter rules, unless the options say otherwise.
     */
    private function evaluate(string $condition, array $document, array $options = [], ?array $fieldClusters = null): bool
    {
        $ast = new AQLParser()->parse($condition);
        $this->assertIsArray($ast, 'Parse error');

        return $this->createEvaluator()->evaluate($fieldClusters ?? $this->getFieldClusters(), $ast['data'], $document, self::ASSET_ID, [
            AQLToESQuery::OPT_TRUSTED_CONDITIONS => true,
            ...$options,
        ]);
    }

    private static function getDocument(): array
    {
        return [
            'workspaceId' => 'ws1',
            'ownerId' => 'owner1',
            'privacy' => 4,
            'collectionPaths' => ['/'.self::COLL_A.'/'.self::COLL_B],
            'createdAt' => '2024-03-15T10:20:30+00:00',
            'deleted' => false,
            'collectionDeleted' => false,
            'status' => 0,
            'fileName' => 'Résumé final-2024.pdf',
            'attrs' => [[
                '_' => [
                    'category_keyword_s' => 'press',
                    'keywords_keyword_m' => ['alpha', 'beta'],
                    'count_number_s' => 42,
                    'other_number_s' => 10,
                    'rate_number_s' => 4.7,
                    'published_boolean_s' => true,
                    'day_date_s' => '2015-05-22',
                    'moment_date-time_s' => '2015-05-22T10:05:42+00:00',
                    'location_geo-point_s' => ['lat' => 48.8566, 'lon' => 2.3522],
                    'restricted_keyword_s' => 'x',
                    'long_title_text_s' => str_repeat('word ', 60),
                    'caption_text_s' => 'L\'été à Paris, aujourd\'hui',
                ],
                'fr' => [
                    'title_text_s' => 'L\'été à Paris',
                ],
                'en' => [
                    'title_text_s' => 'Summer in Paris',
                ],
            ]],
        ];
    }

    private function getFieldClusters(): array
    {
        $registry = $this->createTypeRegistry();
        $t = static fn (string $name): array => ['type' => $registry->getStrictType($name)];

        return [
            [
                'fields' => [
                    'attrs._.category_keyword_s' => $t(KeywordAttributeType::NAME),
                    'attrs._.alias_free_keyword_s' => $t(KeywordAttributeType::NAME),
                    'attrs._.unknown_keyword_s' => $t(KeywordAttributeType::NAME),
                    'attrs._.keywords_keyword_m' => $t(KeywordAttributeType::NAME),
                    'attrs._.count_number_s' => $t(NumberAttributeType::NAME),
                    'attrs._.other_number_s' => $t(NumberAttributeType::NAME),
                    'attrs._.missing_number_number_s' => $t(NumberAttributeType::NAME),
                    'attrs._.rate_number_s' => $t(NumberAttributeType::NAME),
                    'attrs._.published_boolean_s' => $t(BooleanAttributeType::NAME),
                    'attrs._.archived_boolean_s' => $t(BooleanAttributeType::NAME),
                    'attrs._.day_date_s' => $t(DateAttributeType::NAME),
                    'attrs._.moment_date-time_s' => $t(DateTimeAttributeType::NAME),
                    'attrs._.location_geo-point_s' => $t(GeoPointAttributeType::NAME),
                    'attrs._.long_title_text_s' => $t(TextAttributeType::NAME),
                    'attrs._.caption_text_s' => $t(TextAttributeType::NAME),
                    'attrs.{l}.title_text_s' => $t(TextAttributeType::NAME),
                    'attrs.{l}.code_keyword_s' => $t(KeywordAttributeType::NAME),
                    'attrs.{l}.alias_keyword_s' => $t(KeywordAttributeType::NAME),
                ],
                'w' => [],
                'locales' => ['fr', 'en'],
            ],
            [
                'fields' => [
                    'attrs._.restricted_keyword_s' => $t(KeywordAttributeType::NAME),
                ],
                'w' => ['ws2'],
                'locales' => ['fr'],
            ],
        ];
    }

    private function createTypeRegistry(): AttributeTypeRegistry
    {
        $iterator = [];
        foreach ([
            new TextAttributeType(),
            new NumberAttributeType(),
            new GeoPointAttributeType(),
            new KeywordAttributeType(),
            new DateTimeAttributeType(),
            new DateAttributeType(),
            new BooleanAttributeType(),
            new AssetStatusAttributeType(),
            new CollectionPathAttributeType(),
            new PrivacyAttributeType(),
            new IdAttributeType(),
        ] as $service) {
            $iterator[$service::getName()] = $service;
        }

        return $this->typeRegistry ??= new AttributeTypeRegistry($iterator);
    }

    private function createEvaluator(): AQLDocumentEvaluator
    {
        $typeRegistry = $this->createTypeRegistry();

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('find')->willReturnCallback(fn (string $class, mixed $id): ?Collection => $this->createCollectionStub($class, $id));
        $translator = $this->createStub(TranslatorInterface::class);
        $security = $this->createStub(Security::class);

        $functionRegistry = new AQLFunctionRegistry();
        $functionRegistry->register(new ConcatFunction());
        $functionRegistry->register(new SubstringFunction());

        $builtIns = [
            WorkspaceBuiltInAttribute::getKey() => fn () => new WorkspaceBuiltInAttribute($em),
            PrivacyBuiltInAttribute::getKey() => fn () => new PrivacyBuiltInAttribute($translator),
            IdBuiltInAttribute::getKey() => fn () => new IdBuiltInAttribute(),
            FileNameBuiltInAttribute::getKey() => fn () => new FileNameBuiltInAttribute(),
            AssetStatusBuiltInAttribute::getKey() => fn () => new AssetStatusBuiltInAttribute($translator),
            DeletedBuiltInAttribute::getKey() => fn () => new DeletedBuiltInAttribute(),
            CreatedAtBuiltInAttribute::getKey() => fn () => new CreatedAtBuiltInAttribute(),
            CollectionBuiltInAttribute::getKey() => fn () => new CollectionBuiltInAttribute($em, $security),
            DirectCollectionBuiltInAttribute::getKey() => fn () => new DirectCollectionBuiltInAttribute($em, $security),
            SimilarBuiltInAttribute::getKey() => fn () => $this->createSimilarBuiltInAttribute(),
            OwnerBuiltInAttribute::getKey() => fn () => $this->createDisabledOwnerBuiltInAttribute(),
        ];
        $container = new class($builtIns) implements ServiceProviderInterface {
            use ServiceLocatorTrait;
        };

        return new AQLDocumentEvaluator(new AQLConditionResolver(
            new BuiltInAttributeRegistry($container),
            $functionRegistry,
            $typeRegistry,
            new DateNormalizer(),
        ));
    }

    /**
     * Requires a role that the user lacks.
     */
    private function createDisabledOwnerBuiltInAttribute(): OwnerBuiltInAttribute
    {
        $attribute = new OwnerBuiltInAttribute($this->createStub(UserRepositoryInterface::class), 'admin');
        $attribute->setSecurity($this->createStub(Security::class));

        return $attribute;
    }

    private function createSimilarBuiltInAttribute(): SimilarBuiltInAttribute
    {
        $repository = $this->createStub(AssetRepository::class);
        $repository->method('find')->willReturn(new Asset());

        $similarAssetSearch = $this->createStub(SimilarAssetSearch::class);
        $similarAssetSearch->method('createKnnQuery')->willReturn(new Knn('embedding', [0.1, 0.2], 500));

        $security = $this->createStub(Security::class);
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

        $collection = $this->createStub(Collection::class);
        $collection->method('getId')->willReturn($id);
        $collection->method('getAbsolutePath')->willReturn(self::COLLECTION_PATHS[$id]);

        return $collection;
    }
}
