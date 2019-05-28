<?php

declare(strict_types=1);

namespace App\Tests;

class ChangePasswordTest extends ApiTestCase
{
    public function testChangePasswordOK(): void
    {
        $accessToken = $this->authenticateUser('foo@bar.com', 'secret');

        $response = $this->request('POST', '/password/change', [
            'old_password' => 'secret',
            'new_password' => 'secret2',
        ], [], [], null, $accessToken);

        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertEquals(true, $json);

        // Access token should be invalidated
        $response = $this->request('GET', '/me', [], [], [], null, $accessToken);
        $this->assertEquals(401, $response->getStatusCode());

        $this->assertPasswordIsInvalid('foo@bar.com', 'secret');
        $this->assertPasswordIsValid('foo@bar.com', 'secret2');
    }

    public function testChangePasswordWithInvalidOldPassword(): void
    {
        $accessToken = $this->authenticateUser('foo@bar.com', 'secret');

        $response = $this->request('POST', '/password/change', [
            'old_password' => 'invalid_old_secret',
            'new_password' => 'secret2',
        ], [], [], null, $accessToken);

        $this->assertEquals(400, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        unset($json['debug']);
        $this->assertEquals(['error' => 'bad_request', 'error_description' => 'Invalid old password'], $json);

        $this->assertPasswordIsValid('foo@bar.com', 'secret');
        $this->assertPasswordIsInvalid('foo@bar.com', 'secret2');
    }
}
