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
}
