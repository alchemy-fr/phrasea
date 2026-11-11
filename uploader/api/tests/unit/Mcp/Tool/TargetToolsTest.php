<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mcp\Tool;

use Alchemy\McpBundle\Test\AbstractToolTestCase;
use App\Mcp\Tool\TargetTools;

class TargetToolsTest extends AbstractToolTestCase
{
    private TargetTools $tools;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tools = new TargetTools($this->client);
    }

    public function testList(): void
    {
        $this->kernel->willRespond(['member' => [['id' => 't1']], 'totalItems' => 1]);

        $this->assertSame(['items' => [['id' => 't1']], 'page' => 1], $this->tools->list());

        $this->assertLastRequest('GET', '/targets', ['page' => '1']);
    }

    public function testListSearchesByName(): void
    {
        $this->kernel->willRespond(self::emptyCollection());

        $this->tools->list('prod', 2);

        $this->assertLastRequest('GET', '/targets', ['query' => 'prod', 'page' => '2']);
    }

    public function testGet(): void
    {
        $this->kernel->willRespond(['id' => 't1', 'name' => 'Foo']);

        $this->assertSame(['id' => 't1', 'name' => 'Foo'], $this->tools->get('t1'));

        $this->assertLastRequest('GET', '/targets/t1');
    }

    public function testGetFormSchema(): void
    {
        $form = ['version' => 1, 'fields' => [['key' => 'album', 'type' => 'text']]];
        $this->kernel->willRespond($form);

        $this->assertSame(['targetId' => 't1', 'form' => $form], $this->tools->getFormSchema('t1'));

        $this->assertLastRequest('GET', '/targets/t1/form');
    }

    public function testGetFormSchemaOfATargetWithoutForm(): void
    {
        $this->kernel->willRespond(null);

        $this->assertSame(['targetId' => 't1', 'form' => null], $this->tools->getFormSchema('t1'));
    }

    public function testValidateFormData(): void
    {
        $this->kernel->willRespond(['errors' => ['album' => ['This value should not be blank.']]]);

        $this->assertSame(
            ['valid' => false, 'errors' => ['album' => ['This value should not be blank.']]],
            $this->tools->validateFormData('t1', ['album' => '']),
        );

        $this->assertLastRequest('POST', '/form/validate', body: [
            'target' => '/targets/t1',
            'data' => ['album' => ''],
        ]);
    }

    public function testValidateValidFormData(): void
    {
        $this->kernel->willRespond(['errors' => []]);

        $this->assertSame(['valid' => true, 'errors' => []], $this->tools->validateFormData('t1', ['album' => 'Foo']));
    }
}
