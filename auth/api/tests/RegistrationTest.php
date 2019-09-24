<?php

declare(strict_types=1);

namespace App\Tests;

use Hautelook\AliceBundle\PhpUnit\ReloadDatabaseTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RegistrationTest extends WebTestCase
{
    use ReloadDatabaseTrait;

    public function testRegistrationSuccess(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/en/register');

        $form = $crawler->selectButton('register_form[submit]')->form();
        $form['register_form[email]'] = 'test@test.com';
        $form['register_form[plainPassword][first]'] = 'secret';
        $form['register_form[plainPassword][second]'] = 'secret';
        $client->submit($form);
        $client->followRedirect();

        $this->assertContains(
            'Registration almost complete!',
            $client->getResponse()->getContent()
        );

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
    }

    public function testRegistrationUniqueEmail(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/en/register');

        $form = $crawler->selectButton('register_form[submit]')->form();
        $form['register_form[email]'] = 'enabled@bar.com';
        $form['register_form[plainPassword][first]'] = 'secret';
        $form['register_form[plainPassword][second]'] = 'secret';
        $client->submit($form);

        $this->assertContains(
            'Email is already used',
            $client->getResponse()->getContent()
        );
    }
}
