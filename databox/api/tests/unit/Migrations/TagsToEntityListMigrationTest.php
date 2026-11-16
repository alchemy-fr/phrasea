<?php

declare(strict_types=1);

namespace App\Tests\Unit\Migrations;

use DoctrineMigrations\Version20261116203000;
use PHPUnit\Framework\TestCase;

// The migrations are not autoloaded
require_once __DIR__.'/../../../migrations/Version20261116203000.php';

class TagsToEntityListMigrationTest extends TestCase
{
    private const string WS_A = '0b9a0e2c-3f6a-4f4e-9d0a-1f0c2b3a4d5e';
    private const string WS_B = '7c1d2e3f-4a5b-4c6d-8e7f-9a0b1c2d3e4f';
    private const string TAG_A1 = 'a1a1a1a1-0000-4000-8000-000000000001';
    private const string TAG_A2 = 'a2a2a2a2-0000-4000-8000-000000000002';
    private const string TAG_B1 = 'b1b1b1b1-0000-4000-8000-000000000003';

    private const array TAG_WORKSPACES = [
        self::TAG_A1 => self::WS_A,
        self::TAG_A2 => self::WS_A,
        self::TAG_B1 => self::WS_B,
    ];

    public function testDefinitionIdIsTheOneComputedByPostgres(): void
    {
        // SELECT md5('0b9a0e2c-3f6a-4f4e-9d0a-1f0c2b3a4d5e' || 'tags-definition')::uuid
        $hash = md5(self::WS_A.'tags-definition');
        $this->assertSame(
            substr($hash, 0, 8).'-'.substr($hash, 8, 4).'-'.substr($hash, 12, 4).'-'.substr($hash, 16, 4).'-'.substr($hash, 20),
            Version20261116203000::getDefinitionId(self::WS_A),
        );
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', Version20261116203000::getDefinitionId(self::WS_A));
        $this->assertSame(Version20261116203000::getDefinitionId(self::WS_A), Version20261116203000::getDefinitionId(strtoupper(self::WS_A)));
    }

    public function testWithoutTagsActionNothingChanges(): void
    {
        $this->assertNull(Version20261116203000::convertAutomationActions([
            ['type' => 'trash', 'config' => []],
            ['type' => 'attribute', 'config' => ['definitionId' => 'x', 'action' => 'delete', 'value' => 'tags']],
        ], self::TAG_WORKSPACES));
    }

    public function testConvertsAddAndRemoveByWorkspace(): void
    {
        $defA = Version20261116203000::getDefinitionId(self::WS_A);
        $defB = Version20261116203000::getDefinitionId(self::WS_B);

        $this->assertSame([
            ['type' => 'trash', 'config' => []],
            ['type' => 'attribute', 'config' => ['definitionId' => $defA, 'action' => 'add', 'locale' => null, 'value' => self::TAG_A1]],
            ['type' => 'attribute', 'config' => ['definitionId' => $defA, 'action' => 'add', 'locale' => null, 'value' => self::TAG_A2]],
            ['type' => 'attribute', 'config' => ['definitionId' => $defB, 'action' => 'delete', 'locale' => null, 'value' => [self::TAG_B1]]],
            ['type' => 'set_privacy', 'config' => ['privacy' => 2]],
        ], Version20261116203000::convertAutomationActions([
            ['type' => 'trash', 'config' => []],
            ['type' => 'tags', 'config' => ['add' => [self::TAG_A1, strtoupper(self::TAG_A2)], 'remove' => [self::TAG_B1]]],
            ['type' => 'set_privacy', 'config' => ['privacy' => 2]],
        ], self::TAG_WORKSPACES));
    }

    public function testGroupsTheRemovedTagsOfAWorkspaceInOneAction(): void
    {
        $defA = Version20261116203000::getDefinitionId(self::WS_A);

        $this->assertSame([
            ['type' => 'attribute', 'config' => ['definitionId' => $defA, 'action' => 'add', 'locale' => null, 'value' => self::TAG_A1]],
            ['type' => 'attribute', 'config' => ['definitionId' => $defA, 'action' => 'delete', 'locale' => null, 'value' => [self::TAG_A2]]],
        ], Version20261116203000::convertAutomationActions([
            ['type' => 'tags', 'config' => ['add' => [self::TAG_A1], 'remove' => [self::TAG_A2, self::TAG_A2]]],
        ], self::TAG_WORKSPACES));
    }

    public function testUnknownTagsAreLeftOut(): void
    {
        $this->assertSame([
            ['type' => 'trash', 'config' => []],
        ], Version20261116203000::convertAutomationActions([
            ['type' => 'tags', 'config' => ['add' => ['ffffffff-0000-4000-8000-000000000000', 42], 'remove' => []]],
            ['type' => 'trash', 'config' => []],
        ], self::TAG_WORKSPACES));
    }

    public function testAnAutomationLeftWithoutActionGetsAnEmptyList(): void
    {
        $this->assertSame([], Version20261116203000::convertAutomationActions([
            ['type' => 'tags', 'config' => ['add' => ['ffffffff-0000-4000-8000-000000000000']]],
        ], self::TAG_WORKSPACES));
        $this->assertSame([], Version20261116203000::convertAutomationActions([
            ['type' => 'tags', 'config' => null],
        ], self::TAG_WORKSPACES));
    }
}
