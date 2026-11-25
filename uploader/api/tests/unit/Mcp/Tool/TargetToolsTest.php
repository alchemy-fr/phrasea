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

        $this->assertSame(['items' => [['id' => 't1']], 'totalItems' => 1], $this->tools->list());

        $this->assertLastRequest('GET', '/targets', []);
    }

    public function testListSearchesByName(): void
    {
        $this->kernel->willRespond(self::emptyCollection());

        $this->tools->list('prod');

        $this->assertLastRequest('GET', '/targets', ['query' => 'prod']);
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

    public function testListFormFieldOptions(): void
    {
        $option = ['value' => 'e1', 'label' => 'Sea', 'color' => null, 'emoji' => null];
        $this->kernel->willRespond(['member' => [$option], 'hasMore' => true]);

        $this->assertSame(
            ['items' => [$option], 'page' => 1, 'hasNextPage' => true],
            $this->tools->listFormFieldOptions('t1', 'tags'),
        );

        $this->assertLastRequest('GET', '/targets/t1/form-fields/tags/options', ['page' => '1']);
    }

    public function testListFormFieldOptionsFilters(): void
    {
        $this->kernel->willRespond(['member' => [], 'hasMore' => false]);
        $this->tools->listFormFieldOptions('t1', 'my tags', 'sea', page: 2);
        $this->assertLastRequest('GET', '/targets/t1/form-fields/my%20tags/options', ['query' => 'sea', 'page' => '2']);

        $this->kernel->willRespond(['member' => [], 'hasMore' => false]);
        $this->tools->listFormFieldOptions('t1', 'tags', ids: ['e1', 'e2']);
        $this->assertLastRequest('GET', '/targets/t1/form-fields/tags/options', ['ids' => ['e1', 'e2'], 'page' => '1']);
    }

    public function testGetSettings(): void
    {
        $this->kernel->willRespond(['id' => 't1', 'targetUrl' => 'https://example.com']);

        $this->assertSame(['id' => 't1', 'targetUrl' => 'https://example.com'], $this->tools->getSettings('t1'));

        $this->assertLastRequest('GET', '/targets/t1/settings');
    }

    public function testCreate(): void
    {
        $this->kernel->willRespond(['id' => 't1', 'name' => 'Foo'], 201);

        $this->assertSame(['id' => 't1', 'name' => 'Foo'], $this->tools->create('Foo', 'foo', targetUrl: 'https://example.com', allowedGroups: ['g1']));

        $this->assertLastRequest('POST', '/targets', body: [
            'name' => 'Foo',
            'slug' => 'foo',
            'targetUrl' => 'https://example.com',
            'allowedGroups' => ['g1'],
        ]);
    }

    public function testUpdate(): void
    {
        $this->kernel->willRespond(['id' => 't1']);

        $this->tools->update('t1', name: 'Renamed', allowedGroups: []);

        $this->assertLastRequest('PATCH', '/targets/t1', body: [
            'name' => 'Renamed',
            'allowedGroups' => [],
        ]);
    }

    public function testUpdateClearsTheGivenFields(): void
    {
        $this->kernel->willRespond(['id' => 't1']);

        $this->tools->update('t1', targetUrl: 'https://example.com', clear: ['authorizationKey', 'description', 'name']);

        $this->assertLastRequest('PATCH', '/targets/t1', body: [
            'targetUrl' => 'https://example.com',
            'description' => null,
            'authorizationKey' => null,
        ]);
    }

    public function testDelete(): void
    {
        $this->kernel->willRespond(null, 204);

        $this->assertSame(['deleted' => 't1'], $this->tools->delete('t1'));

        $this->assertLastRequest('DELETE', '/targets/t1');
    }

    public function testUpdateForm(): void
    {
        $form = ['version' => 1, 'fields' => []];
        $this->kernel->willRespond($form);

        $this->assertSame(['targetId' => 't1', 'form' => $form], $this->tools->updateForm('t1', $form));

        $this->assertLastRequest('PUT', '/targets/t1/form', body: ['form' => $form]);
    }

    public function testRemoveForm(): void
    {
        $this->kernel->willRespond(null);

        $this->assertSame(['targetId' => 't1', 'form' => null], $this->tools->updateForm('t1', null));

        $this->assertLastRequest('PUT', '/targets/t1/form', body: ['form' => null]);
    }

    public function testGetData(): void
    {
        $this->kernel->willRespond(['source' => 'web']);
        $this->assertSame(['targetId' => 't1', 'data' => ['source' => 'web']], $this->tools->getData('t1'));
        $this->assertLastRequest('GET', '/targets/t1/data');

        $this->kernel->willRespond(null);
        $this->assertSame(['targetId' => 't1', 'data' => null], $this->tools->getData('t1'));
    }

    public function testUpdateData(): void
    {
        $this->kernel->willRespond(['source' => 'web']);
        $this->assertSame(['targetId' => 't1', 'data' => ['source' => 'web']], $this->tools->updateData('t1', ['source' => 'web']));
        $this->assertLastRequest('PUT', '/targets/t1/data', body: ['data' => ['source' => 'web']]);

        $this->kernel->willRespond(null);
        $this->assertSame(['targetId' => 't1', 'data' => null], $this->tools->updateData('t1', null));
        $this->assertLastRequest('PUT', '/targets/t1/data', body: ['data' => null]);
    }
}
