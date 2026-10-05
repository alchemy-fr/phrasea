<?php

declare(strict_types=1);

namespace Alchemy\ESBundle\Tests\Command;

use Alchemy\ESBundle\Command\DebugElasticSearchIndexCommand;
use Alchemy\ESBundle\Tests\ESClientMockTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class DebugElasticSearchIndexCommandTest extends TestCase
{
    use ESClientMockTrait;

    private function createTester(): CommandTester
    {
        $client = $this->createClientMock();
        $this->indices->method('get')->willReturnCallback(fn (array $params) => $this->createResponse([
            $params['index'] => ['settings' => ['index' => ['number_of_shards' => '1']], 'mappings' => []],
        ]));

        return new CommandTester(new DebugElasticSearchIndexCommand(
            $this->createIndexManagerMock(['asset' => 'asset_dev', 'tag' => 'tag_dev']),
            $client,
        ));
    }

    public function testSingleIndex(): void
    {
        $tester = $this->createTester();
        $this->indices->expects($this->once())->method('get')->with(['index' => 'asset_dev']);

        $code = $tester->execute(['--index' => 'asset']);

        $this->assertSame(Command::SUCCESS, $code);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('read-only', $display);
        $this->assertStringContainsString('Index "asset" (physical name: asset_dev)', $display);
        $this->assertStringContainsString('"number_of_shards": "1"', $display);
    }

    public function testAllIndicesByDefault(): void
    {
        $tester = $this->createTester();
        $this->indices->expects($this->exactly(2))->method('get');

        $tester->execute([]);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('all 2 configured indices', $display);
        $this->assertStringContainsString('physical name: asset_dev', $display);
        $this->assertStringContainsString('physical name: tag_dev', $display);
    }
}
