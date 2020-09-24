<?php

declare(strict_types=1);

namespace App\Tests;

class ResetPasswordTest extends AbstractPasswordTest
{
    public function testResetPasswordOK(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $request = $this->createResetPasswordRequest('foo@bar.com');

        $uri = sprintf(
            '/en/security/password-reset/%s/%s',
            $request->getId(),
            $request->getToken()
        );
        $client->request('GET', $uri);
        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        $client->submitForm('Reset password', [
            'reset_password_form[new_password][first]' => 'new_secret',
            'reset_password_form[new_password][second]' => 'new_secret',
        ]);

        $this->assertTrue(
            $client->getResponse()->isRedirect('/en/security/password-reset/changed')
        );

        $this->assertPasswordIsInvalid('foo@bar.com', 'secret');
        $this->assertPasswordIsValid('foo@bar.com', 'new_secret');
        $this->assertPasswordResetRequestCount(0);
    }
}
