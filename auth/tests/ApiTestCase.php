<?php

declare(strict_types=1);

namespace App\Tests;

use Hautelook\AliceBundle\PhpUnit\RefreshDatabaseTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Client;
use Symfony\Component\HttpFoundation\Response;

abstract class ApiTestCase extends WebTestCase
{
    use RefreshDatabaseTrait;

    const CLIENT_ID = 'mobile-app_12356789abcdefghijklmnopqrstuvwx';
    const CLIENT_SECRET = 'cli3nt_s3cr3t';

    /**
     * @var Client
     */
    protected $client;

    protected function request(string $method, string $uri, $params = [], array $files = [], array $server = [], ?string $accessToken = null): Response
    {
        if (null !== $accessToken) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$accessToken;
            $server['CONTENT_TYPE'] = 'application/json';
        }

        $this->client->request($method, $uri, $params, $files, $server);

        /** @var Response $response */
        $response = $this->client->getResponse();

        return $response;
    }

    /**
     * @return string The access token
     */
    protected function authenticateUser(string $email, string $password): string
    {
        $response = $this->request('POST', '/oauth/v2/token', [
            'username' => $email,
            'password' => $password,
            'grant_type' => 'password',
            'client_id' => self::CLIENT_ID,
            'client_secret' => self::CLIENT_SECRET,
        ]);
        $json = json_decode($response->getContent(), true);

        return $json['access_token'];
    }

    protected function setUp()
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->client->disableReboot();
    }
}
