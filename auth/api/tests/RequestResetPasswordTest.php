<?php

declare(strict_types=1);

namespace App\Tests;

class RequestResetPasswordTest extends AbstractPasswordTest
{
    public function testRequestResetPasswordWithExistingEmail(): void
    {
        $this->markTestSkipped('TODO disable notifier in test env');
        // TODO disable notifier in test env
        $response = $this->request('POST', '/password/reset-request', [
            'email' => 'foo@bar.com',
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);

        $this->assertEquals(true, $json);
        $this->assertPasswordResetRequestCount(1);
    }

    public function testMultipleRequestsWillGenerateOnlyOneRequest(): void
    {
        $this->request('POST', '/password/reset-request', [
            'email' => 'foo@bar.com',
        ]);
        $this->request('POST', '/password/reset-request', [
            'email' => 'foo@bar.com',
        ]);
        $this->assertPasswordResetRequestCount(1);
    }

    public function testRequestResetPasswordWithNonExistingEmail(): void
    {
        $response = $this->request('POST', '/password/reset-request', [
            'email' => 'baz@bar.com',
        ]);
        // Must return 200 otherwise it would allow attackers to scan emails in database.
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);

        $this->assertEquals(true, $json);
        $this->assertPasswordResetRequestCount(0);
    }

    public function testRequestResetPasswordWillSendEmail(): void
    {
        $this->markTestSkipped('TODO disable notifier in test env');
        // TODO disable notifier in test env
        $response = $this->request('POST', '/password/reset-request', [
            'email' => 'foo@bar.com',
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertEquals(true, $json);
    }
}
