<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Tests;

use Alchemy\StorageBundle\Storage\PathGenerator;
use PHPUnit\Framework\TestCase;

class PathGeneratorTest extends TestCase
{
    /**
     * @dataProvider extensionProvider
     */
    public function testGeneratedPathIsSane(?string $extension, string $expectedSuffix): void
    {
        $path = new PathGenerator()->generatePath($extension, 'files/');

        $this->assertMatchesRegularExpression('#^files/[0-9a-f]{2}/[0-9a-f]{2}/[0-9a-f-]{36}'.preg_quote($expectedSuffix, '#').'$#', $path);
        $this->assertSame($path, trim($path));
    }

    public function extensionProvider(): array
    {
        return [
            ['jpg', '.jpg'],
            ['JPG', '.JPG'],
            [null, ''],
            ['', ''],
            ["pdf\n", '.pdf'],
            ['pdf ', '.pdf'],
            ['../etc', '.etc'],
            ["\t", ''],
        ];
    }
}
