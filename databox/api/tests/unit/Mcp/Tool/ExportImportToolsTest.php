<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mcp\Tool;

use Alchemy\McpBundle\Test\AbstractToolTestCase;
use App\Mcp\Tool\ExportImportTools;

class ExportImportToolsTest extends AbstractToolTestCase
{
    private ExportImportTools $tools;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tools = new ExportImportTools($this->client);
    }

    public function testCreateExport(): void
    {
        $this->kernel->willRespond(['id' => 'e1', 'status' => 0], 201);

        $this->assertSame(['id' => 'e1', 'status' => 0], $this->tools->createExport([2 => 'a1', 5 => 'a2'], ['d1']));
        $this->assertLastRequest('POST', '/asset-exports', body: [
            'assets' => ['a1', 'a2'],
            'renditions' => ['d1'],
            'ignoreRoi' => false,
        ]);
    }

    public function testGetExport(): void
    {
        $this->kernel->willRespond(['id' => 'e1', 'status' => 2, 'downloadUrl' => 'https://s3/export.zip']);

        $this->assertSame('https://s3/export.zip', $this->tools->getExport('e1')['downloadUrl']);
        $this->assertLastRequest('GET', '/asset-exports/e1');
    }

    public function testListImports(): void
    {
        $this->kernel->willRespond(self::emptyCollection());

        $this->tools->listImports('w1', page: 2);

        $this->assertLastRequest('GET', '/asset-imports', ['workspace' => 'w1', 'page' => '2', 'limit' => '30']);
    }

    public function testListImportErrors(): void
    {
        $this->kernel->willRespond(['member' => [['line' => 3, 'message' => 'Invalid URL']], 'totalItems' => 1]);

        $result = $this->tools->listImportErrors('i1', limit: 100);

        $this->assertSame([['line' => 3, 'message' => 'Invalid URL']], $result['items']);
        $this->assertLastRequest('GET', '/asset-imports/i1/errors', ['page' => '1', 'limit' => '100']);
    }
}
