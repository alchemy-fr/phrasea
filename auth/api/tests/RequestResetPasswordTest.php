<?php

declare(strict_types=1);

namespace App\Tests;

class RequestResetPasswordTest extends AbstractPasswordTest
{
    public function testRequestResetPasswordWithExistingEmail(): void
    {
        $response = $this->request(null, 'POST', '/en/password/reset-request', [
            'username' => 'foo@bar.com',
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);

        $this->assertEquals(true, $json);
        $this->assertPasswordResetRequestCount(1);
    }

    public function testMultipleRequestsWillGenerateOnlyOneRequest(): void
    {
        $this->request(null, 'POST', '/en/password/reset-request', [
            'username' => 'foo@bar.com',
        ]);
        $this->request(null, 'POST', '/en/password/reset-request', [
            'username' => 'foo@bar.com',
        ]);
        $this->assertPasswordResetRequestCount(1);
    }

    public function testRequestResetPasswordWithNonExistingEmail(): void
    {
        $response = $this->request(null, 'POST', '/en/password/reset-request', [
            'username' => 'baz@bar.com',
        ]);
        // Must return 200 otherwise it would allow attackers to scan usernames in database.
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);

        $this->assertEquals(true, $json);
        $this->assertPasswordResetRequestCount(0);
    }

    public function testRequestResetPasswordWillSendEmail(): void
    {
        $response = $this->request(null, 'POST', '/en/password/reset-request', [
            'username' => 'foo@bar.com',
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertEquals(true, $json);
    }
}
