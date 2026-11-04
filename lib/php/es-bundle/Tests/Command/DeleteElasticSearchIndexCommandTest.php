<?php

declare(strict_types=1);

namespace Alchemy\ESBundle\Tests\Command;

use Alchemy\ESBundle\Command\DeleteElasticSearchIndexCommand;
use Alchemy\ESBundle\Service\IndexRemover;
use Alchemy\ESBundle\Tests\ESClientMockTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class DeleteElasticSearchIndexCommandTest extends TestCase
{
    use ESClientMockTrait;

    private const CLUSTER = [
        'asset_dev_2026-01-01-000000' => ['asset_dev'],
        'asset_dev_2025-12-01-000000' => [],
        'asset_dev_2025-11-01-000000' => [],
        'tag_dev' => [],
    ];

    private const OLDS = ['asset_dev_2025-12-01-000000', 'asset_dev_2025-11-01-000000'];

    /** @var list<string> */
    private array $deleted = [];

    private function createTester(array $cluster = self::CLUSTER): CommandTester
    {
        $client = $this->createClientMock();
        $this->indices->method('getAlias')->willReturn($this->createResponse(self::aliasesResponse($cluster)));
        $this->deleted = [];
        $this->indices->method('delete')->willReturnCallback(function (array $params) {
            $this->deleted[] = $params['index'];

            return $this->createResponse([]);
        });

        $remover = new IndexRemover($this->createIndexManagerMock(['asset' => 'asset_dev', 'tag' => 'tag_dev']), $client);

        return new CommandTester(new DeleteElasticSearchIndexCommand($remover));
    }

    public function testNothingToRemoveNeedsNoConfirmation(): void
    {
        $tester = $this->createTester();

        $code = $tester->execute(['--index' => 'tag'], ['interactive' => false]);

        $this->assertSame([], $this->deleted);
        $this->assertSame(Command::SUCCESS, $code);
        $this->assertStringContainsString('Nothing to remove', $tester->getDisplay());
    }

    public function testPlanIsDisplayedBeforeAskingForConfirmation(): void
    {
        $tester = $this->createTester();
        $tester->setInputs(['no']);

        $tester->execute(['--index' => 'asset', '--olds-only' => true]);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('index "asset"', $display);
        $this->assertStringContainsString('only the old dated indices', $display);
        $this->assertStringContainsString('2 physical indices will be', $display);
        $this->assertStringContainsString('asset_dev_2025-12-01-000000', $display);
        $this->assertStringContainsString('asset_dev_2025-11-01-000000', $display);
        $this->assertStringContainsString('Do you really want to delete these indices?', $display);
    }

    public function testAnsweringNoAbortsWithoutDeleting(): void
    {
        $tester = $this->createTester();
        $tester->setInputs(['no']);

        $code = $tester->execute(['--index' => 'asset', '--olds-only' => true]);

        $this->assertSame(Command::SUCCESS, $code);
        $this->assertSame([], $this->deleted);
        $this->assertStringContainsString('Aborted, nothing was deleted', $tester->getDisplay());
    }

    public function testDefaultAnswerIsNo(): void
    {
        $tester = $this->createTester();
        $tester->setInputs(['']);

        $tester->execute(['--index' => 'asset', '--olds-only' => true]);

        $this->assertSame([], $this->deleted);
        $this->assertStringContainsString('Aborted', $tester->getDisplay());
    }

    public function testAnsweringYesDeletes(): void
    {
        $tester = $this->createTester();
        $tester->setInputs(['yes']);

        $code = $tester->execute(['--index' => 'asset', '--olds-only' => true]);

        $this->assertSame(Command::SUCCESS, $code);
        $this->assertSame(self::OLDS, $this->deleted);
        $this->assertStringContainsString('2 indices removed', $tester->getDisplay());
    }

    public function testNonInteractiveWithoutForceRefuses(): void
    {
        $tester = $this->createTester();

        $code = $tester->execute(['--index' => 'asset', '--olds-only' => true], ['interactive' => false]);

        $this->assertSame(Command::FAILURE, $code);
        $this->assertSame([], $this->deleted);
        $this->assertStringContainsString('Use --force to proceed', $tester->getDisplay());
    }

    public function testForceSkipsConfirmation(): void
    {
        $tester = $this->createTester();

        $code = $tester->execute(['--index' => 'asset', '--olds-only' => true, '--force' => true], ['interactive' => false]);

        $this->assertSame(Command::SUCCESS, $code);
        $this->assertSame(self::OLDS, $this->deleted);
        $this->assertStringNotContainsString('Do you really want', $tester->getDisplay());
        $this->assertStringContainsString('2 indices removed', $tester->getDisplay());
    }

    public function testDefaultScopeIsTheAliasedIndexOnly(): void
    {
        $tester = $this->createTester();

        $tester->execute(['--index' => 'asset', '--force' => true], ['interactive' => false]);

        $this->assertSame(['asset_dev_2026-01-01-000000'], $this->deleted);
        $this->assertStringContainsString('the currently aliased index only', $tester->getDisplay());
    }

    public function testRemoveOldsAndForcePrefixOnEveryIndex(): void
    {
        $tester = $this->createTester();

        $tester->execute(['--remove-olds' => true, '--force-prefix' => true, '--force' => true], ['interactive' => false]);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('all configured indices', $display);
        $this->assertStringContainsString('every physical index prefixed', $display);
        $this->assertSame(['asset_dev_2026-01-01-000000', 'asset_dev_2025-12-01-000000', 'asset_dev_2025-11-01-000000', 'tag_dev'], $this->deleted);
    }
}
