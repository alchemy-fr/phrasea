<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Api\Model\Input\Attribute\AssetAttributeBatchUpdateInput;
use App\Api\Model\Input\Attribute\AttributeActionInput;
use App\Api\Model\Input\InputLimits;
use App\Tests\Functional\AbstractDataboxTestCase;
use App\Tests\Functional\Api\Asset\AssetApiTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The ID lists of the API inputs are bounded: they end up in SQL "IN" clauses (one parameter per ID).
 */
final class InputLimitsTest extends AbstractDataboxTestCase
{
    use AssetApiTestTrait;

    /**
     * @return iterable<string, array{string, array<string, mixed>}>
     */
    public static function idListEndpointProvider(): iterable
    {
        yield 'prepare-delete' => ['/assets/prepare-delete', []];
        yield 'assets delete-multiple' => ['/assets/delete-multiple', []];
        yield 'assets restore-multiple' => ['/assets/restore-multiple', []];
        yield 'move' => ['/assets/move', ['destination' => '/collections/'.Uuid::uuid4()->toString()]];
        yield 'copy' => ['/assets/copy', ['destination' => '/collections/'.Uuid::uuid4()->toString()]];
        yield 'add-to-collection' => ['/assets/add-to-collection', ['destination' => '/collections/'.Uuid::uuid4()->toString()]];
        yield 'collections delete-multiple' => ['/collections/delete-multiple', []];
        yield 'collections restore-multiple' => ['/collections/restore-multiple', []];
    }

    #[DataProvider('idListEndpointProvider')]
    public function testIdListsAreBounded(string $uri, array $extra): void
    {
        $this->request('POST', $uri, self::OWNER, array_merge($extra, [
            'ids' => self::uuids(InputLimits::MAX_IDS + 1),
        ]));
        $this->assertResponseStatusCodeSame(422);
    }

    public function testResolvedEntitiesAreBounded(): void
    {
        $this->request('POST', '/assets/entities', self::OWNER, [
            'entities' => array_map(static fn (string $id): string => '/assets/'.$id, self::uuids(InputLimits::MAX_IDS + 1)),
        ]);
        $this->assertResponseStatusCodeSame(422);
    }

    public function testAttributeBatchUpdateAssetsAreBounded(): void
    {
        $this->request('POST', '/attributes/batch-update', self::OWNER, [
            'workspaceId' => Uuid::uuid4()->toString(),
            'actions' => [],
            'assets' => self::uuids(InputLimits::MAX_IDS + 1),
        ]);
        $this->assertResponseStatusCodeSame(422);
    }

    public function testAssetAttributeActionAssetsAreBounded(): void
    {
        $assetId = $this->createBatchTargetAsset();

        $this->request('POST', '/assets/'.$assetId.'/attributes', self::OWNER, [
            'actions' => [
                ['action' => 'delete', 'name' => 'Description', 'assets' => self::uuids(InputLimits::MAX_IDS + 1)],
            ],
        ]);
        $this->assertResponseStatusCodeSame(422);
    }

    public function testDistinctAssetsOfAllTheActionsAreBounded(): void
    {
        $assetId = $this->createBatchTargetAsset();
        $half = intdiv(InputLimits::MAX_IDS, 2) + 1;

        $this->request('POST', '/assets/'.$assetId.'/attributes', self::OWNER, [
            'actions' => [
                ['action' => 'delete', 'name' => 'Description', 'assets' => self::uuids($half)],
                ['action' => 'delete', 'name' => 'Description', 'assets' => self::uuids($half)],
            ],
        ]);
        $this->assertResponseStatusCodeSame(422);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidRegexReplaceProvider(): iterable
    {
        yield 'pattern too long' => [['value' => str_repeat('a', AttributeActionInput::REGEX_MAX_LENGTH + 1)]];
        yield 'unknown flags' => [['value' => 'a+', 'flags' => 'zz']];
    }

    #[DataProvider('invalidRegexReplaceProvider')]
    public function testRegexReplaceIsBounded(array $replace): void
    {
        $assetId = $this->createBatchTargetAsset();

        $this->request('POST', '/assets/'.$assetId.'/attributes', self::OWNER, [
            'actions' => [
                array_merge(['action' => 'replace', 'regex' => true, 'name' => 'Description', 'replaceWith' => 'b'], $replace),
            ],
        ]);
        $this->assertResponseStatusCodeSame(422);
    }

    /**
     * Validated without being run: SQLite has no REGEXP_REPLACE.
     */
    public function testShortRegexReplaceWithValidFlagsIsAccepted(): void
    {
        $action = new AttributeActionInput();
        $action->action = 'replace';
        $action->regex = true;
        $action->value = '(foo|bar)+';
        $action->flags = 'gi';
        $action->replaceWith = 'baz';
        $input = new AssetAttributeBatchUpdateInput();
        $input->actions = [$action];

        $violations = static::getContainer()->get(ValidatorInterface::class)->validate($input);
        $this->assertCount(0, $violations, (string) $violations);
    }

    public function testSortIsBounded(): void
    {
        $this->request('POST', '/attribute-definitions/sort', self::OWNER, self::uuids(InputLimits::MAX_IDS + 1));
        $this->assertResponseStatusCodeSame(400);
    }

    public function testSortRequiresAList(): void
    {
        $this->request('POST', '/attribute-definitions/sort', self::OWNER, ['a' => 1]);
        $this->assertResponseStatusCodeSame(400);
    }

    public function testPrepareDeleteIgnoresUnknownIdsInShareCount(): void
    {
        $this->createOwnedWorkspace();
        $asset = $this->createAsset(['ownerId' => self::OWNER]);

        $this->request('POST', '/shares', self::ADMIN, ['assets' => ['/assets/'.$asset->getId()]]);
        $this->assertResponseStatusCodeSame(201);

        $data = $this->request('POST', '/assets/prepare-delete', self::OWNER, [
            'ids' => [$asset->getId(), Uuid::uuid4()->toString()],
        ])->toArray();
        $this->assertResponseIsSuccessful();
        $this->assertTrue($data['canDelete']);
        $this->assertSame(1, $data['shareCount']);
    }

    private function createBatchTargetAsset(): string
    {
        $this->createOwnedWorkspace();

        return $this->createAsset(['ownerId' => self::OWNER])->getId();
    }

    /**
     * @return string[]
     */
    private static function uuids(int $count): array
    {
        $ids = [];
        for ($i = 0; $i < $count; ++$i) {
            $ids[] = Uuid::uuid4()->toString();
        }

        return $ids;
    }
}
