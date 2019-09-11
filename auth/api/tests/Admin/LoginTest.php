<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Tests\AbstractStateFullTestCase;

class LoginTest extends AbstractStateFullTestCase
{
    public function testLoginAndRedirectToAdmin(): void
    {
        $client = $this->client;
        $client->followRedirects();

        $crawler = $client->request('GET', '/admin/login');

        $form = $crawler->selectButton('login_submit')->form();
        $form['email'] = 'foo@bar.com';
        $form['password'] = 'secret';
        $client->submit($form);

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertContains(
            '<meta name="generator" content="EasyAdmin"',
            $client->getResponse()->getContent()
        );
    }

    public function testLoginWithANonAdminUserGenerates403(): void
    {
        $client = $this->client;
        $client->followRedirects();

        $crawler = $client->request('GET', '/admin/login');

        $form = $crawler->selectButton('login_submit')->form();
        $form['email'] = 'enabled@bar.com';
        $form['password'] = 'secret';
        $client->submit($form);

        $this->assertEquals(403, $client->getResponse()->getStatusCode());
    }
}
