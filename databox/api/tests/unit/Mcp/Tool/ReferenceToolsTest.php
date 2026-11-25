<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mcp\Tool;

use Alchemy\McpBundle\Test\AbstractToolTestCase;
use App\Mcp\Tool\ReferenceTools;

class ReferenceToolsTest extends AbstractToolTestCase
{
    private ReferenceTools $tools;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tools = new ReferenceTools($this->client);
    }

    public function testListFieldTypes(): void
    {
        $this->kernel->willRespond(['member' => [['@id' => '/field-types/text', 'name' => 'text', 'displayName' => 'Text']], 'totalItems' => 1]);

        $this->assertSame([['name' => 'text', 'displayName' => 'Text']], $this->tools->listFieldTypes()['items']);
        $this->assertLastRequest('GET', '/field-types');
    }

    public function testListBuiltInAttributes(): void
    {
        $this->kernel->willRespond(self::emptyCollection());

        $this->tools->listBuiltInAttributes();

        $this->assertLastRequest('GET', '/built-in-attributes');
    }

    public function testListMetadataTags(): void
    {
        $this->kernel->willRespond(self::emptyCollection());
        $this->tools->listMetadataTags('IPTC:Key');
        $this->assertLastRequest('GET', '/metadata-tags', ['query' => 'IPTC:Key']);

        $this->kernel->willRespond(self::emptyCollection());
        $this->tools->listMetadataTags();
        $this->assertLastRequest('GET', '/metadata-tags', []);
    }

    public function testListLocalesFiltersOnTheCodeAndTheNames(): void
    {
        $locales = [
            'member' => [
                ['id' => 'fr', 'name' => 'French', 'nativeName' => 'Français'],
                ['id' => 'pt_BR', 'name' => 'Portuguese (Brazil)', 'nativeName' => 'Português (Brasil)'],
                ['id' => 'pt', 'name' => 'Portuguese', 'nativeName' => 'Português'],
            ],
            'totalItems' => 3,
        ];

        $this->kernel->willRespond($locales);
        $this->assertSame(3, $this->tools->listLocales()['totalItems']);
        $this->assertLastRequest('GET', '/locales');

        $this->kernel->willRespond($locales);
        $this->assertSame(['pt_BR', 'pt'], array_column($this->tools->listLocales('PT')['items'], 'id'));

        $this->kernel->willRespond($locales);
        $this->assertSame(['fr'], array_column($this->tools->listLocales('franç')['items'], 'id'));
    }
}
