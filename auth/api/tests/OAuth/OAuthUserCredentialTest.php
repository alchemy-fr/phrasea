<?php

declare(strict_types=1);

namespace App\Tests;

class OAuthUserCredentialTest extends ApiTestCase
{
    public function testAuthenticationOK(): void
    {
        $response = $this->request('POST', '/oauth/v2/token', [
            'username' => 'foo@bar.com',
            'password' => 'secret',
            'grant_type' => 'password',
            'client_id' => self::CLIENT_ID,
            'client_secret' => self::CLIENT_SECRET,
        ]);
        $this->assertEquals(200, $response->getStatusCode());

        $json = json_decode($response->getContent(), true);
        $this->assertRegExp('#^[a-zA-Z0-9]+$#', $json['access_token']);
        $this->assertRegExp('#^[a-zA-Z0-9]+$#', $json['refresh_token']);
        $this->assertArrayHasKey('scope', $json);
        $this->assertEquals('7776000', $json['expires_in']);
        $this->assertEquals('bearer', $json['token_type']);
    }

    public function testAuthenticationInvalidPassword(): void
    {
        $response = $this->request('POST', '/oauth/v2/token', [
            'username' => 'foo@bar.com',
            'password' => 'invalid_secret',
            'grant_type' => 'password',
            'client_id' => self::CLIENT_ID,
            'client_secret' => self::CLIENT_SECRET,
        ]);
        $this->assertEquals(400, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertEquals('invalid_grant', $json['error']);
        $this->assertEquals('Invalid username and password combination', $json['error_description']);
    }

    public function testAuthenticationInvalidGrantType(): void
    {
        $response = $this->request('POST', '/oauth/v2/token', [
            'grant_type' => 'foo',
        ]);
        $this->assertEquals(400, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $json);
        $this->assertEquals('invalid_request', $json['error']);
    }

    public function testInvalidClientSecret(): void
    {
        $response = $this->request('POST', '/oauth/v2/token', [
            'username' => 'foo@bar.com',
            'password' => 'invalid_secret',
            'grant_type' => 'password',
            'client_id' => self::CLIENT_ID,
            'client_secret' => 'invalid_client_secret',
        ]);
        $this->assertEquals(400, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $json);
        $this->assertEquals('invalid_client', $json['error']);
    }

    public function testInvalidClientId(): void
    {
        $response = $this->request('POST', '/oauth/v2/token', [
            'username' => 'foo@bar.com',
            'password' => 'invalid_secret',
            'grant_type' => 'password',
            'client_id' => 'invalid_client_id',
            'client_secret' => self::CLIENT_SECRET,
        ]);
        $this->assertEquals(400, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $json);
        $this->assertEquals('invalid_client', $json['error']);
    }
}
