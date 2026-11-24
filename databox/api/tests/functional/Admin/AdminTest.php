<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

class AdminTest extends AbstractDataboxAdminTestCase
{
    public function testAdmin()
    {
        $this->doTestAllPages();
    }

    /**
     * Elasticsearch may be empty or unreachable: the Assets card and the Latest assets block then
     * show an error, but the page is still rendered.
     */
    public function testHomePage(): void
    {
        $this->doTestHomePage([
            'Assets',
            'Collections',
            'Workspaces',
            'Workflows',
            'Failed workflows',
            'Exports',
            'Shares',
            'Baskets',
            'Active users',
            'Failed messages',
        ], [
            'Latest assets',
            'Audit log',
            'Workflows',
            'Exports',
        ]);
    }

    public function testRealtimePage(): void
    {
        $this->doTestRealtimePage();
    }

    public function testQueuesPage(): void
    {
        $this->doTestQueuesPage();
    }
}
