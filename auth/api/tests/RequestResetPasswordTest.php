<?php

declare(strict_types=1);

namespace App\Tests;

class RequestResetPasswordTest extends AbstractPasswordTest
{
    public function testRequestResetPasswordWithExistingEmail(): void
    {
        $client = $this->client;
        $client->disableReboot();

        $client->request('GET', '/en/security/password-reset/request');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        $client->submitForm('request_password_reset_form[submit]', [
            'request_password_reset_form[username]' => 'foo@bar.com',
        ]);

        $this->assertTrue(
            $client->getResponse()->isRedirect('/en/security/password-reset/requested')
        );

        $this->assertPasswordResetRequestCount(1);
    }

    public function testRequestResetPasswordWithNonExistingEmail(): void
    {
        $client = $this->client;
        $client->disableReboot();

        $client->request('GET', '/en/security/password-reset/request');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        $client->submitForm('request_password_reset_form[submit]', [
            'request_password_reset_form[username]' => 'baz@bar.com',
        ]);

        $this->assertTrue(
            $client->getResponse()->isRedirect('/en/security/password-reset/requested')
        );

        $this->assertPasswordResetRequestCount(0);
    }
}
