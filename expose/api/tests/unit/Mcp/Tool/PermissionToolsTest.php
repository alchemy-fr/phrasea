<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mcp\Tool;

use Alchemy\McpBundle\Test\AbstractToolTestCase;
use App\Mcp\Tool\PermissionTools;
use Mcp\Exception\ToolCallException;
use Symfony\Component\Yaml\Yaml;

class PermissionToolsTest extends AbstractToolTestCase
{
    private PermissionTools $tools;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tools = new PermissionTools($this->client);
    }

    public function testListTheEntriesOfAnObject(): void
    {
        $this->kernel->willRespond([
            ['id' => 'ace1', 'userType' => 'user', 'userId' => 'u1', 'mask' => 5],
        ]);

        $result = $this->tools->list('publication', 'p1');

        $this->assertSame([
            ['id' => 'ace1', 'userType' => 'user', 'userId' => 'u1', 'mask' => 5, 'permissions' => ['VIEW', 'EDIT']],
        ], $result['items']);
        $this->assertLastRequest('GET', '/permissions/aces', [
            'objectType' => 'publication',
            'objectId' => 'p1',
        ]);
    }

    public function testListWithTypeWideEntries(): void
    {
        $this->kernel->willRespond([]);

        $this->assertSame(['items' => []], $this->tools->list('profile', 'pr1', true));

        $this->assertLastRequest('GET', '/permissions/aces', [
            'objectType' => 'profile',
            'objectId' => 'pr1',
            'objectIdWildcard' => '1',
        ]);
    }

    public function testListTheTypeWideEntriesOnly(): void
    {
        $this->kernel->willRespond([]);

        $this->tools->list('publication', includeTypeWide: true);

        $this->assertLastRequest('GET', '/permissions/aces', [
            'objectType' => 'publication',
            'objectId' => '',
        ]);
    }

    public function testSet(): void
    {
        $this->kernel->willRespond(['id' => 'ace1', 'mask' => 36]);

        $result = $this->tools->set('publication', 'group', 'g1', ['EDIT', 'OPERATOR'], 'p1');

        $this->assertSame(['id' => 'ace1', 'mask' => 36, 'permissions' => ['EDIT', 'OPERATOR']], $result);
        $this->assertLastRequest('PUT', '/permissions/ace', body: [
            'objectType' => 'publication',
            'objectId' => 'p1',
            'userType' => 'group',
            'userId' => 'g1',
            'mask' => 36,
        ]);
    }

    public function testSetOnTheWholeType(): void
    {
        $this->kernel->willRespond(['id' => 'ace1', 'mask' => 2]);

        $this->tools->set('profile', 'user', '__ALL_USERS__', ['CREATE']);

        $this->assertLastRequest('PUT', '/permissions/ace', body: [
            'objectType' => 'profile',
            'userType' => 'user',
            'userId' => '__ALL_USERS__',
            'mask' => 2,
        ]);
    }

    public function testSetRejectsAnUnknownPermission(): void
    {
        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('Unknown permission "MASTER"');

        try {
            $this->tools->set('publication', 'user', 'u1', ['VIEW', 'MASTER'], 'p1');
        } finally {
            $this->assertSame([], $this->kernel->calls);
        }
    }

    public function testRemove(): void
    {
        $this->kernel->willRespond(true);

        $this->assertSame([
            'removed' => ['objectType' => 'publication', 'objectId' => 'p1', 'userType' => 'user', 'userId' => 'u1'],
        ], $this->tools->remove('publication', 'user', 'u1', 'p1'));

        $this->assertLastRequest('DELETE', '/permissions/ace', body: [
            'objectType' => 'publication',
            'objectId' => 'p1',
            'userType' => 'user',
            'userId' => 'u1',
        ]);
    }

    public function testSearchUsers(): void
    {
        $this->kernel->willRespond([['id' => 'u1', 'username' => 'john']]);

        $this->assertSame([
            'items' => [['id' => 'u1', 'username' => 'john']],
            'offset' => 30,
            'hasMore' => false,
        ], $this->tools->searchUsers('john', 30));

        $this->assertLastRequest('GET', '/permissions/users', [
            'query' => 'john',
            'limit' => '30',
            'offset' => '30',
        ]);
    }

    public function testSearchGroupsFullPageMayHaveMore(): void
    {
        $this->kernel->willRespond(array_fill(0, 30, ['id' => 'g']));

        $result = $this->tools->searchGroups();

        $this->assertTrue($result['hasMore']);
        $this->assertLastRequest('GET', '/permissions/groups', [
            'limit' => '30',
            'offset' => '0',
        ]);
    }

    public function testConstantsMatchTheAclConfiguration(): void
    {
        $config = Yaml::parseFile(__DIR__.'/../../../../config/packages/alchemy_acl.yaml')['alchemy_acl'];

        $this->assertEqualsCanonicalizing(array_keys($config['objects']), PermissionTools::OBJECT_TYPES);
        $this->assertEqualsCanonicalizing($config['enabled_permissions'], PermissionTools::PERMISSIONS);
    }
}
