<?php

declare(strict_types=1);

namespace App\Tests;

class OAuthClientCredentialsTest extends AbstractTestCase
{
    public function testClientCredentialAccessTokenOK(): void
    {
        $response = $this->request(null, 'POST', '/oauth/v2/token', [
            'grant_type' => 'client_credentials',
            'client_id' => self::CLIENT_ID,
            'client_secret' => self::CLIENT_SECRET,
        ]);
        $json = json_decode($response->getContent(), true);
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertResponseToken($json, false);
    }

    public function testAllowedSingleScope(): void
    {
        $response = $this->request(null, 'POST', '/oauth/v2/token', [
            'grant_type' => 'client_credentials',
            'scope' => 'scope1',
            'client_id' => self::CLIENT_ID,
            'client_secret' => self::CLIENT_SECRET,
        ]);
        $json = json_decode($response->getContent(), true);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertResponseToken($json, false);
        $this->assertEquals('scope1', $json['scope']);
    }

    public function testUnsupportedSingleScope(): void
    {
        $response = $this->request(null, 'POST', '/oauth/v2/token', [
            'grant_type' => 'client_credentials',
            'scope' => 'invalid_scope',
            'client_id' => self::CLIENT_ID,
            'client_secret' => self::CLIENT_SECRET,
        ]);
        $this->assertEquals(400, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $json);
        $this->assertEquals('invalid_scope', $json['error']);
    }

    public function testNotAllowedSingleScope(): void
    {
        $response = $this->request(null, 'POST', '/oauth/v2/token', [
            'grant_type' => 'client_credentials',
            'scope' => 'scope2',
            'client_id' => self::CLIENT_ID,
            'client_secret' => self::CLIENT_SECRET,
        ]);
        $this->assertEquals(400, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $json);
        $this->assertEquals('invalid_scope', $json['error']);
        $this->assertEquals('Scope "scope2" is not allowed for this client.', $json['error_description']);
    }

    public function testAllowedMultipleScope(): void
    {
        $response = $this->request(null, 'POST', '/oauth/v2/token', [
            'grant_type' => 'client_credentials',
            'scope' => 'scope1 scope3',
            'client_id' => self::CLIENT_ID,
            'client_secret' => self::CLIENT_SECRET,
        ]);
        $json = json_decode($response->getContent(), true);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertResponseToken($json, false);
        $this->assertEquals('scope1 scope3', $json['scope']);
    }

    public function testUnsupportedMultipleScope(): void
    {
        $response = $this->request(null, 'POST', '/oauth/v2/token', [
            'grant_type' => 'client_credentials',
            'scope' => 'scope1 invalid_scope',
            'client_id' => self::CLIENT_ID,
            'client_secret' => self::CLIENT_SECRET,
        ]);
        $this->assertEquals(400, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $json);
        $this->assertEquals('invalid_scope', $json['error']);
    }

    public function testNotAllowedMultipleScope(): void
    {
        $response = $this->request(null, 'POST', '/oauth/v2/token', [
            'grant_type' => 'client_credentials',
            'scope' => 'scope1 scope2',
            'client_id' => self::CLIENT_ID,
            'client_secret' => self::CLIENT_SECRET,
        ]);
        $this->assertEquals(400, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $json);
        $this->assertEquals('invalid_scope', $json['error']);
        $this->assertEquals('Scope "scope2" is not allowed for this client.', $json['error_description']);
    }
}
