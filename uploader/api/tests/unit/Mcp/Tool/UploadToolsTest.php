<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mcp\Tool;

use Alchemy\McpBundle\Test\AbstractToolTestCase;
use App\Mcp\Tool\UploadTools;

class UploadToolsTest extends AbstractToolTestCase
{
    private UploadTools $tools;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tools = new UploadTools($this->client);
    }

    public function testUploadFromUrl(): void
    {
        $this->kernel->willRespond(true);

        $this->assertSame(['result' => true], $this->tools->uploadFromUrl('https://example.com/a.jpg', 't1'));

        $this->assertLastRequest('POST', '/downloads', body: [
            'url' => 'https://example.com/a.jpg',
            'target' => '/targets/t1',
        ]);
    }

    public function testUploadFromUrlWithFormDataAndData(): void
    {
        $this->kernel->willRespond(true);

        $this->tools->uploadFromUrl(
            'https://example.com/a.jpg',
            't1',
            ['album' => 'Foo'],
            ['targetAsset' => 'a1', 'targetRendition' => 'original'],
        );

        $this->assertLastRequest('POST', '/downloads', body: [
            'url' => 'https://example.com/a.jpg',
            'target' => '/targets/t1',
            'formData' => ['album' => 'Foo'],
            'data' => ['targetAsset' => 'a1', 'targetRendition' => 'original'],
        ]);
    }

    public function testUploadFromUrlWithInvalidFormData(): void
    {
        $this->kernel->willRespond(['errors' => ['album' => ['This value should not be blank.']]]);

        $this->assertSame(
            ['errors' => ['album' => ['This value should not be blank.']]],
            $this->tools->uploadFromUrl('https://example.com/a.jpg', 't1', ['album' => '']),
        );
    }
}
