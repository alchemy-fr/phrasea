<?php

declare(strict_types=1);

namespace App\Tests;

use Alchemy\AdminBundle\Tests\AbstractAdminTest;

class AdminTest extends AbstractAdminTest
{
    public function testAdmin()
    {
        $this->doTestAllPages();
    }
}
