<?php

declare(strict_types=1);

namespace App\Tests;

use Hautelook\AliceBundle\PhpUnit\RefreshDatabaseTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Client;
use Symfony\Component\HttpFoundation\Response;

class OAuthTest extends WebTestCase
{
    use RefreshDatabaseTrait;

    /**
     * @var Client
     */
    protected $client;

    public function testAuthenticationOK(): void
    {
        $response = $this->request('POST', '/oauth/v2/token', [
            'username' => 'foo@bar.com',
            'password' => 'secret',
            'grant_type' => 'password',
            'client_id' => 'mobile_app',
            'client_secret' => 'client_secret',
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
            'client_id' => 'mobile_app',
            'client_secret' => 'client_secret',
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

    protected function request(string $method, string $uri, $params = [], array $files = [], array $headers = []): Response
    {
        $this->client->request($method, $uri, $params, $files);

        return $this->client->getResponse();
    }

    protected function setUp()
    {
        parent::setUp();

        $this->client = static::createClient();
    }
}
