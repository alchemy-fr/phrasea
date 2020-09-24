<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Tests\AbstractStateFullTestCase;

class LoginTest extends AbstractStateFullTestCase
{
    public function testLoginAndRedirectToAdmin(): void
    {
        $client = $this->client;
        $client->request('GET', '/admin/login');

        $response = $client->getResponse();
        $this->assertEquals(302, $response->getStatusCode());
    }
}
