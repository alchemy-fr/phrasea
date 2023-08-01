<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use Alchemy\AdminBundle\Tests\AbstractAdminTest;

class AdminTest extends AbstractAdminTest
{
    public function testAdmin()
    {
        var_dump(getenv('APP_ENV'));
        var_dump(getenv('ADMIN_CLIENT_ID'));
        var_dump(getenv('DATABOX_API_URL'));
        $kernel = self::bootKernel();
        var_dump($kernel->getEnvironment());

        $this->doTestAllPages();
    }
}
