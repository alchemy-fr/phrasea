<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use Alchemy\AdminBundle\Tests\AbstractAdminTest;

class AdminTest extends AbstractAdminTest
{
    public function testAdmin()
    {
        $this->doTestAllPages();
    }
}
