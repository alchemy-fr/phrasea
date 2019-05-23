<?php

declare(strict_types=1);

namespace App\Tests;

class ResetPasswordTest extends ApiTestCase
{
    public function testResetPasswordWithExistingEmail(): void
    {
        $response = $this->request('POST', '/password/reset', [
            'email' => 'foo@bar.com',
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertEquals(true, $json);
    }

    public function testResetPasswordWithNonExistingEmail(): void
    {
        $response = $this->request('POST', '/password/reset', [
            'email' => 'baz@bar.com',
        ]);
        // Must return 200 otherwise it would allow attackers to scan emails in database.
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertEquals(true, $json);
    }

    public function testResetPasswordWillSendEmail(): void
    {
        $response = $this->request('POST', '/password/reset', [
            'email' => 'foo@bar.com',
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertEquals(true, $json);


    }
}
