<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

class AdminTest extends AbstractDataboxAdminTestCase
{
    public function testAdmin()
    {
        $this->doTestAllPages();
    }
}
