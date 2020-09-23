<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\AbstractPasswordTest;

class RequestResetPasswordTest extends AbstractPasswordTest
{
    public function testApiRequestResetPasswordWithExistingEmail(): void
    {
        $response = $this->request(null, 'POST', '/en/password-reset/request', [
            'username' => 'foo@bar.com',
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);

        $this->assertEquals(true, $json);
        $this->assertPasswordResetRequestCount(1);
    }

    public function testApiMultipleRequestsWillGenerateOnlyOneRequest(): void
    {
        $this->request(null, 'POST', '/en/password-reset/request', [
            'username' => 'foo@bar.com',
        ]);
        $this->assertPasswordResetRequestCount(1);
        $this->request(null, 'POST', '/en/password-reset/request', [
            'username' => 'foo@bar.com',
        ]);
        $this->assertPasswordResetRequestCount(1);
    }

    public function testApiRequestResetPasswordWithNonExistingEmail(): void
    {
        $response = $this->request(null, 'POST', '/en/password-reset/request', [
            'username' => 'baz@bar.com',
        ]);
        // Must return 200 otherwise it would allow attackers to scan usernames in database.
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);

        $this->assertEquals(true, $json);
        $this->assertPasswordResetRequestCount(0);
    }

    public function testApiRequestResetPasswordWillSendEmail(): void
    {
        $response = $this->request(null, 'POST', '/en/password-reset/request', [
            'username' => 'foo@bar.com',
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertEquals(true, $json);
    }
}
