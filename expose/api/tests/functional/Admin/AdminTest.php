<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use Alchemy\AdminBundle\Tests\AbstractAdminTestCase;

class AdminTest extends AbstractAdminTestCase
{
    public function testAdmin()
    {
        $this->doTestAllPages();
    }

    public function testHomePage(): void
    {
        $this->doTestHomePage([
            'Publications',
            'Assets',
            'Download requests',
            'Publication shares',
            'Profiles',
            'Failed messages',
        ], [
            'Latest publications',
            'Latest assets',
            'Latest download requests',
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
