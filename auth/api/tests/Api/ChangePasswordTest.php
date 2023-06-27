<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\AbstractPasswordTest;

class ChangePasswordTest extends AbstractPasswordTest
{
    public function testApiChangePasswordOK(): void
    {
        $accessToken = $this->authenticateUser('foo@bar.com', 'secret');

        $response = $this->request($accessToken, 'POST', '/password/change', [
            'old_password' => 'secret',
            'new_password' => 'secret2',
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals(true, $json);

        // Access token should be invalidated
        $response = $this->request($accessToken, 'GET', '/me');
        $this->assertEquals(401, $response->getStatusCode());

        $this->assertPasswordIsInvalid('foo@bar.com', 'secret');
        $this->assertPasswordIsValid('foo@bar.com', 'secret2');
    }

    public function testApiChangePasswordWillInvalidResetPasswordRequests(): void
    {
        $this->createResetPasswordRequest('foo@bar.com');
        $this->assertPasswordResetRequestCount(1);

        $accessToken = $this->authenticateUser('foo@bar.com', 'secret');

        $this->request($accessToken, 'POST', '/password/change', [
            'old_password' => 'secret',
            'new_password' => 'secret2',
        ]);

        $this->assertPasswordResetRequestCount(0);
    }

    public function testApiChangePasswordWithInvalidOldPassword(): void
    {
        $accessToken = $this->authenticateUser('foo@bar.com', 'secret');

        $response = $this->request($accessToken, 'POST', '/password/change', [
            'old_password' => 'invalid_old_secret',
            'new_password' => 'secret2',
        ]);

        $this->assertEquals(400, $response->getStatusCode());
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        unset($json['debug']);
        $this->assertEquals(['error' => 'bad_request', 'error_description' => 'Invalid old password'], $json);

        $this->assertPasswordIsValid('foo@bar.com', 'secret');
        $this->assertPasswordIsInvalid('foo@bar.com', 'secret2');
    }
}
