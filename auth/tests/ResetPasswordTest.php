<?php

declare(strict_types=1);

namespace App\Tests;

class ResetPasswordTest extends AbstractPasswordTest
{
    public function testResetPasswordOK(): void
    {
        $request = $this->createResetPasswordRequest('foo@bar.com');

        $uri = sprintf(
            '/password/reset/%s/%s',
            $request->getId(),
            $request->getToken()
        );
        $this->client->request('GET', $uri, [
            'email' => 'foo@bar.com',
        ]);
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        $this->client->submitForm('Reset password', [
            'reset_password_form[new_password][first]' => 'new_secret',
            'reset_password_form[new_password][second]' => 'new_secret',
        ]);

        $this->assertTrue(
            $this->client->getResponse()->isRedirect('/password/reset/changed')
        );

        $this->assertPasswordIsInvalid('foo@bar.com', 'secret');
        $this->assertPasswordIsValid('foo@bar.com', 'new_secret');
        $this->assertPasswordResetRequestCount(0);
    }
}
